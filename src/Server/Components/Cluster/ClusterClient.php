<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-18
 * Time: 下午2:14
 */

namespace Server\Components\Cluster;

use Server\Memory\Pool;
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
    protected $token;
    protected $receive_call = [];

    public function __construct($ip, $port, $onConnect)
    {
        $this->onConnect = $onConnect;
        $this->ip = $ip;
        $this->port = $port;
        $this->client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $this->pack = new ClusterPack();
        $this->client->set($this->pack->getProbufSet());
        $this->token = 0;
        $this->client->on("connect", function ($cli) {
            $this->isClose = false;
            if (!empty($this->reconnect_tick)) {
                swoole_timer_clear($this->reconnect_tick);
                $this->reconnect_tick = null;
            }
            sd_call_user_func($this->onConnect, $this);
        });
        $this->client->on("receive", function ($cli, $recdata) {
            $data = $this->pack->unPack($recdata);
            $token = $data['t'];
            if (array_key_exists($token, $this->receive_call)) {
                $this->receive_call[$token]($data['r']);
                unset($this->receive_call[$token]);
            }
        });
        $this->client->on("error", function ($cli) {
            if (empty($this->reconnect_tick)) {
                $this->reconnect_tick = swoole_timer_tick(1000, [$this, 'reConnect']);
            }
        });
        $this->client->on("close", function ($cli) {
            $this->isClose = true;
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
        if ($this->isClose) {
            $this->client->connect($this->ip, $this->port);
        }
    }

    /**
     * 发送数据
     * @param $method_name
     * @param $params
     * @return bool
     */
    public function send($method_name, $params)
    {
        $this->token++;
        if ($this->token > 655360) {
            $this->token = 0;
        }
        if ($this->client->isConnected()) {
            $this->client->send($this->pack->pack(['m' => $method_name, 'p' => $params, 't' => $this->token]));
            return "[$this->ip][$method_name][$this->token]";
        } else {
            return false;
        }
    }

    /**
     * @param $name
     * @param $arguments
     * @return bool
     */
    public function __call($name, $arguments)
    {
        return $this->send($name, $arguments);
    }

    /**
     * 添加回调
     * @param $token
     * @param callable|null $set
     * @return
     */
    public function getTokenResult($token, callable $set = null)
    {
        return Pool::getInstance()->get(ClusterCoroutine::class)->init($token, $this->receive_call, $set);
    }

    /**
     * 主动断开
     */
    public function close()
    {
        $this->isClose = true;
        if (!empty($this->reconnect_tick)) {
            swoole_timer_clear($this->reconnect_tick);
            $this->reconnect_tick = null;
        }
    }
}