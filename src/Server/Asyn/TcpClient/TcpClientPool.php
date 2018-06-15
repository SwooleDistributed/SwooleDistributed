<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-2-27
 * Time: 上午10:13
 */

namespace Server\Asyn\TcpClient;


use Server\Asyn\AsynPool;
use Server\CoreBase\PortManager;
use Server\CoreBase\SwooleException;
use Server\Memory\Pool;
use Server\Pack\IPack;

/**
 * 连接池版
 * Class TcpClientPool
 * @package Server\Asyn\TcpClient
 */
class TcpClientPool extends AsynPool
{
    const AsynName = 'tcp_client';

    public $connect;
    protected $port;
    protected $host;
    protected $set;
    protected $name;
    /**
     * @var IPack
     */
    protected $pack;
    protected $tcpClient_max_count;
    protected $package_length_type_length;
    protected $ssl_enable;

    /**
     * TcpClientPool constructor.
     * @param $config
     * @param $config_name
     * @param $connect
     * @throws SwooleException
     */
    public function __construct($config, $config_name, $connect)
    {
        parent::__construct($config);
        $this->connect = $connect;
        $this->pack = PortManager::createPack($this->config->get("tcpClient.$config_name.pack_tool"));
        $this->ssl_enable = $this->config->get("tcpClient.$config_name.ssl_enable", false);
        $this->set = $this->pack->getProbufSet();
        list($this->host, $this->port) = explode(':', $connect);
        $this->client_max_count = $this->config->get('tcpClient.asyn_max_count', 10);
    }

    /**
     * 获取同步
     * @throws SwooleException
     */
    public function getSync()
    {
        throw new SwooleException('暂时没有TcpClient的同步方法');
    }

    /**
     * @return string
     */
    public function getAsynName()
    {
        return self::AsynName . ":" . $this->connect;
    }

    /**
     * @param $send
     * @param $callback
     * @param bool $oneway
     * @return int
     * @internal param $data
     */
    public function send($send, $callback, $oneway = false)
    {
        $data['token'] = $this->addTokenCallback($callback);
        $data['send'] = $this->pack->pack($send);
        $data['oneway'] = $oneway;
        $this->execute($data);
        return $data['token'];
    }

    /**
     * @param $data
     */
    public function execute($data)
    {
        $client = $this->shiftFromPool($data);
        if ($client) {
            if (!$client->isConnected()) {
                unset($client);
                $this->client_count--;
                $this->prepareOne();
                $this->commands->push($data);
                return;
            }
            $client->token = $data['token'];
            $client->send($data['send']);
            //单向的返回null直接回收
            if ($data['oneway'] ?? false) {
                $result['token'] = $data['token'];
                $result['result'] = null;
                $this->distribute($result);
                unset($client->token);
                $this->pushToPool($client);
            }
            if (count($this->commands) > 0) {//有残留的任务
                $command = $this->commands->shift();
                $this->execute($command);
            }
        }
    }

    /**
     * 准备一个Client
     */
    public function prepareOne()
    {
        if (parent::prepareOne()) {
            if ($this->ssl_enable) {
                $client = new \swoole_client(SWOOLE_SOCK_TCP | SWOOLE_SSL, SWOOLE_SOCK_ASYNC);
            } else {
                $client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
            }
            $client->set($this->set);
            $client->on("connect", function ($cli) {
                $this->pushToPool($cli);
            });
            $client->on("receive", function ($cli, $recdata) {
                if (isset($cli->token)) {
                    try {
                        $packdata = $this->pack->unPack($recdata);
                    } catch (\Exception $e) {
                        return null;
                    }
                    $data['token'] = $cli->token;
                    $data['result'] = $packdata;
                    $this->distribute($data);
                    unset($cli->token);
                    $this->pushToPool($cli);
                }
            });
            $client->on("error", function ($cli) {
                $cli->close();
            });
            $client->on("close", function ($cli) {

            });
            $client->connect($this->host, $this->port);
        }
    }

    /**
     * 协程的发送
     * @param $send
     * @param bool $oneway
     * @param callable|null $set
     * @return TcpClientRequestCoroutine
     */
    public function coroutineSend($send, $oneway = false, callable $set = null)
    {
        return Pool::getInstance()->get(TcpClientRequestCoroutine::class)->init($this, $send, $oneway, $set);
    }

    /**
     * 设置path
     * @param $path
     * @param $data
     * @return mixed
     */
    public function setPath($path, &$data)
    {
        $data['path'] = $path;
        return $data;
    }

    /**
     * 销毁Client
     * @param $client
     */
    protected function destoryClient($client)
    {
        $client->close();
    }

    public function setName($name)
    {
        $this->name = $name;
    }
}