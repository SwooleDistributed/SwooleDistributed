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
    protected $reconnect_tick;
    protected $isClose = false;
    public function __construct($ip, $port, $onConnect)
    {
        $this->onConnect = $onConnect;
        $this->ip = $ip;
        $this->port = $port;
        $this->client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $this->pack = new ClusterPack();
        $this->client->set($this->pack->getProbufSet());
        $this->client->on("connect", function ($cli) {
            if (!empty($this->reconnect_tick)) {
                swoole_timer_clear($this->reconnect_tick);
                $this->reconnect_tick = null;
            }
            call_user_func($this->onConnect, $this);
        });
        $this->client->on("receive", function ($cli, $recdata) {

        });
        $this->client->on("error", function ($cli) {
            if (empty($this->reconnect_tick)) {
                $this->reconnect_tick = swoole_timer_tick(1000, [$this, 'reConnect']);
            }
        });
        $this->client->on("close", function ($cli) {
            if (empty($this->reconnect_tick)) {
                $this->reconnect_tick = swoole_timer_tick(1000, [$this, 'reConnect']);
            }
        });
        $this->client->on("BufferEmpty", function ($cli) {

        });
        $this->client->on("BufferFull", function ($cli) {

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
        if (!empty($this->reconnect_tick)) {
            swoole_timer_clear($this->reconnect_tick);
            $this->reconnect_tick = null;
        }
    }
}