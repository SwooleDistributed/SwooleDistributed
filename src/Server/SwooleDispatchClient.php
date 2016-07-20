<?php
namespace Server;

use Noodlehaus\Exception;
use Server\CoreBase\ControllerFactory;
use Server\CoreBase\Loader;
use Server\DataBase\DbConnection;
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
     * @var \Redis
     */
    protected $redis_client;

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
        //同步redis连接，用于存储
        $this->redis_client = new \Redis();
        if ($this->redis_client->pconnect($this->config['redis']['ip'], $this->config['redis']['port']) == false) {
            throw new Exception($this->redis_client->getLastError());
        }
        if ($this->redis_client->auth($this->config['redis']['password']) == false) {
            throw new Exception($this->redis_client->getLastError());
        }
        $this->redis_client->select($this->config['redis']['select']);
    }

    /**
     * 服务器发来消息
     * @param $cli
     * @param $data
     */
    public function onClientReceive($cli, $client_data)
    {
        print_r("Get Message\n");
        $data = substr($client_data, SwooleMarco::HEADER_LENGTH);
        $unserialize_data = unserialize($data);
        $type = $unserialize_data['type']??'';
        $message = $unserialize_data['message']??'';
        switch ($type) {
            case SwooleMarco::MSG_TYPE_SEND_GROUP://发送群消息
                //转换为batch
                $type = SwooleMarco::MSG_TYPE_SEND_BATCH;
                $uids = $this->redis_client->hGetAll(SwooleMarco::redis_group_hash_name_prefix.$message['groupId']);
                if($uids!=null&&count($uids)>0){
                    $message['uids'] = $uids;
                }else{
                    break;
                }
            case SwooleMarco::MSG_TYPE_SEND_BATCH://发送消息
                $usids = $this->redis_client->hMGet(SwooleMarco::redis_uid_usid_hash_name, $message['uids']);
                $temp_dic = [];
                foreach ($usids as $uid => $usid) {
                    if(!empty($usid)) {
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
                break;
            case SwooleMarco::MSG_TYPE_SEND_ALL://发送广播
                foreach ($this->server_clients as $client) {
                    $client->send($client_data);
                }
                break;
            case SwooleMarco::MSG_TYPE_SEND://发送给uid
                $usid = $this->redis_client->hGet(SwooleMarco::redis_uid_usid_hash_name, $message['uid']);
                if(empty($usid)||!key_exists($usid, $this->server_clients)){
                    break;
                }
                $client = $this->server_clients[$usid];
                $client->send($client_data);
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