<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-18
 * Time: 下午2:14
 */

namespace Server\Components\Cluster;


use Server\Pack\ClusterPack;

class ClusterClient
{
    protected $client;
    protected $pack;
    protected $ip;
    protected $port;
    protected $onConnect;
    protected $isClose;

    public function __construct($ip, $port, $onConnect)
    {
        $this->onConnect = $onConnect;
        $this->ip = $ip;
        $this->port = $port;
        $this->client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $this->pack = new ClusterPack();
        $this->client->set($this->pack->getProbufSet());
        $this->client->on("connect", function ($cli) {
            call_user_func($this->onConnect, $this);
        });
        $this->client->on("receive", function ($cli, $recdata) {

        });
        $this->client->on("error", function ($cli) {

        });
        $this->client->on("close", function ($cli) {
            if (!$this->isClose) {
                swoole_timer_after(1000, [$this, 'reConnect']);
            }
        });
        $this->client->connect($this->ip, $this->port);
    }

    /**
     * 重连
     */
    public function reConnect()
    {
        if (!$this->isClose) {
            $this->client->connect($this->ip, $this->port);
        }
    }

    /**
     * 发送数据
     * @param $method_name
     * @param $params
     */
    public function send($method_name, $params)
    {
        $this->client->send($this->pack->pack(['m' => $method_name, 'p' => $params]));
    }

    /**
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $this->send($name, $arguments);
    }

    /**
     * 主动断开
     */
    public function close()
    {
        $this->isClose = true;
        $this->client = null;
    }

    /**
     * ping
     */
    public function ping()
    {
        if ($this->client->isConnected()) {
            $this->client->send($this->pack->pack(ClusterPack::PING));
        }
    }
}