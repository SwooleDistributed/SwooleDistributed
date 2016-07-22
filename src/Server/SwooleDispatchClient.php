<?php
namespace Server;

use Noodlehaus\Exception;
use Server\CoreBase\ControllerFactory;
use Server\CoreBase\Loader;
use Server\DataBase\DbConnection;
use Server\DataBase\RedisAsynPool;
use Server\Pack\IPack;
use Server\Route\IRoute;

/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-14
 * Time: 上午9:18
 */
class SwooleDispatchClient extends SwooleServer
{
    /**
     * server_clients
     * @var array
     */
    protected $server_clients = [];


    /**
     * @var RedisAsynPool
     */
    protected $redis_pool;
    protected $redis_pool_process;

    /**
     * SwooleDispatchClient constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 设置配置
     */
    public function setConfig()
    {
        $this->socket_type = SWOOLE_SOCK_UDP;
        $this->socket_name = $this->config['dispatch_server']['socket'];
        $this->port = $this->config['dispatch_server']['port'];
        $this->name = $this->config['dispatch_server']['name'];
        $this->user = $this->config->get('dispatch_server.set.user', '');
        $this->worker_num = $this->config['dispatch_server']['set']['worker_num'];
    }

    /**
     * 设置服务器配置参数
     * @return array
     */
    public function setServerSet()
    {
        $set = $this->config['dispatch_server']['set'];
        return $set;
    }

    /**
     * beforeSwooleStart
     */
    public function beforeSwooleStart()
    {
        //创建redis异步连接池进程
        $this->redis_pool_process = new \swoole_process(function ($process) {
            $redisAsynPool = new RedisAsynPool();
            $redisAsynPool->server_init($this->config, $this, $process);
        }, false, 2);
        $this->server->addProcess($this->redis_pool_process);
    }

    /**
     * onStart
     * @param $serv
     * @throws Exception
     */
    public function onSwooleStart($serv)
    {
        parent::onSwooleStart($serv);
    }

    public function onSwooleWorkerStart($serv, $workerId)
    {
        parent::onSwooleWorkerStart($serv, $workerId);
        if (!$serv->taskworker) {
            //异步redis连接池
            $this->redis_pool = new RedisAsynPool();
            $this->redis_pool->worker_init($this->redis_pool_process, $workerId);
        }
        //同步redis连接，用于存储
        $this->redis_client = new \Redis();
        if ($this->redis_client->pconnect($this->config['redis']['ip'], $this->config['redis']['port']) == false) {
            throw new SwooleException($this->redis_client->getLastError());
        }
        if ($this->redis_client->auth($this->config['redis']['password']) == false) {
            throw new SwooleException($this->redis_client->getLastError());
        }
        $this->redis_client->select($this->config['redis']['select']);
    }

    /**
     * UDP 消息
     * @param $server
     * @param string $data
     * @param array $client_info
     */
    public function onSwoolePacket($server, $data, $client_info)
    {
        parent::onSwoolePacket($server, $data, $client_info);
        if ($data == $this->config['dispatch_server']['password']) {
            for ($i = 0; $i < $this->worker_num; $i++) {
                if ($i == $server->worker_id) continue;
                $data = $this->packSerevrMessageBody(SwooleMarco::ADD_SERVER, $client_info['address']);
                $server->sendMessage($data, $i);
            }
            $this->addServerClient($client_info['address']);
        }
    }

    /**
     * PipeMessage
     * @param $serv
     * @param $from_worker_id
     * @param $message
     */
    public function onSwoolePipeMessage($serv, $from_worker_id, $message)
    {
        parent::onSwoolePipeMessage($serv, $from_worker_id, $message);
        $data = unserialize($message);
        switch ($data['type']) {
            case SwooleMarco::ADD_SERVER:
                $address = $data['message'];
                $this->addServerClient($address);
                break;
            case SwooleMarco::MSG_TYPE_REDIS_MESSAGE:
                $this->redis_pool->_distribute($data['message']);
                break;
        }
    }

    /**
     * 增加一个服务器连接
     * @param $address
     */
    private function addServerClient($address)
    {
        if (key_exists(ip2long($address), $this->server_clients)) {
            return;
        }
        $client = new \swoole_client(SWOOLE_TCP, SWOOLE_SOCK_ASYNC);
        $client->set($this->probuf_set);
        $client->on("connect", [$this, 'onClientConnect']);
        $client->on("receive", [$this, 'onClientReceive']);
        $client->on("close", [$this, 'onClientClose']);
        $client->on("error", [$this, 'onClientError']);
        $client->address = $address;
        $client->connect($address, $this->config['server']['dispatch_port']);
        $this->server_clients[ip2long($address)] = $client;
    }

    /**
     * 连接到服务器
     * @param $cli
     */
    public function onClientConnect($cli)
    {
        print_r("connect\n");
        $data = $this->packSerevrMessageBody(SwooleMarco::MSG_TYPE_USID, ip2long($cli->address));
        $cli->send($this->encode($data));
    }

    /**
     * 服务器发来消息
     * @param $cli
     * @param $data
     */
    public function onClientReceive($cli, $client_data)
    {
        $data = substr($client_data, SwooleMarco::HEADER_LENGTH);
        $unserialize_data = unserialize($data);
        $type = $unserialize_data['type']??'';
        $message = $unserialize_data['message']??'';
        switch ($type) {
            case SwooleMarco::MSG_TYPE_SEND_GROUP://发送群消息
                //转换为batch
                $this->redis_pool->hGetAll(SwooleMarco::redis_group_hash_name_prefix . $message['groupId'], function ($uids) use ($message) {
                    if ($uids != null && count($uids) > 0) {
                        $this->redis_pool->hMGet(SwooleMarco::redis_uid_usid_hash_name, $uids, function ($usids) use ($message) {
                            $temp_dic = [];
                            foreach ($usids as $uid => $usid) {
                                if (!empty($usid)) {
                                    $temp_dic[$usid][] = $uid;
                                }
                            }
                            foreach ($temp_dic as $usid => $uids) {
                                $client = $this->server_clients[$usid]??null;
                                if ($client == null) continue;
                                $client->send($this->encode($this->packSerevrMessageBody(SwooleMarco::MSG_TYPE_SEND_BATCH, [
                                    'data' => $message['data'],
                                    'uids' => $uids
                                ])));
                            }
                        });
                    }
                });
                break;
            case SwooleMarco::MSG_TYPE_SEND_BATCH://发送消息
                $this->redis_pool->hMGet(SwooleMarco::redis_uid_usid_hash_name, $message['uids'], function ($usids) use ($message) {
                    $temp_dic = [];
                    foreach ($usids as $uid => $usid) {
                        if (!empty($usid)) {
                            $temp_dic[$usid][] = $uid;
                        }
                    }
                    foreach ($temp_dic as $usid => $uids) {
                        $client = $this->server_clients[$usid]??null;
                        if ($client == null) {
                            continue;
                        }
                        $client->send($this->encode($this->packSerevrMessageBody(SwooleMarco::MSG_TYPE_SEND_BATCH, [
                            'data' => $message['data'],
                            'uids' => $uids
                        ])));
                    }
                });
                break;
            case SwooleMarco::MSG_TYPE_SEND_ALL://发送广播
                foreach ($this->server_clients as $client) {
                    $client->send($client_data);
                }
                break;
            case SwooleMarco::MSG_TYPE_SEND://发送给uid
                $this->redis_pool->hGet(SwooleMarco::redis_uid_usid_hash_name, $message['uid'], function ($usid) use ($client_data) {
                    if (empty($usid) || !key_exists($usid, $this->server_clients)) {
                        return;
                    }
                    $client = $this->server_clients[$usid];
                    $client->send($client_data);
                });
                break;
        }
    }

    /**
     * 服务器断开连接
     * @param $cli
     */
    public function onClientClose($cli)
    {
        print_r("close\n");
        $cli->close();
        unset($this->server_clients[ip2long($cli->address)]);
        unset($cli);
    }

    /**
     * 服务器连接失败
     * @param $cli
     */
    public function onClientError($cli)
    {
        print_r("close\n");
        $cli->close();
        unset($this->server_clients[$cli->address]);
        unset($cli);
    }
}