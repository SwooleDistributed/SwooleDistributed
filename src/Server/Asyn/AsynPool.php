<?php
/**
 * 异步连接池基类
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-25
 * Time: 上午11:40
 */

namespace Server\Asyn;


abstract class AsynPool implements IAsynPool
{
    const MAX_TOKEN = 65536 * 8;
    /**
     * @var Config
     */
    public $config;
    protected $commands;
    protected $pool;
    protected $callBacks;
    protected $clients;
    protected $worker_id;
    protected $server;
    protected $swoole_server;
    protected $token = 1;
    protected $client_max_count;
    protected $client_count;
    /**
     * @var AsynPoolManager
     */
    protected $asyn_manager;

    public function __construct($config)
    {
        $this->callBacks = [];
        $this->clients = [];
        $this->commands = new \SplQueue();
        $this->pool = new \SplQueue();
        $this->config = $config;
    }

    /**
     * 添加一个callback获得一个token
     * @param $callback
     * @return int
     */
    public function addTokenCallback($callback)
    {
        $token = $this->token;
        $this->callBacks[$token] = $callback;
        $this->token++;
        if ($this->token >= self::MAX_TOKEN) {
            $this->token = 1;
        }
        return $token;
    }

    /**
     * 超时时需要处理下
     * 销毁垃圾
     * @param $token
     */
    public function destoryGarbage($token)
    {
        unset($this->callBacks[$token]);
        if (array_key_exists($token, $this->clients)) {
            $this->destoryClient($this->clients[$token]);
        }
        $this->client_count--;
        unset($this->clients[$token]);
    }

    /**
     * 销毁Client
     * @param $client
     */
    abstract protected function destoryClient($client);

    /**
     * 分发消息
     * @param $data
     */
    public function distribute($data)
    {
        $callback = $this->callBacks[$data['token']]??null;
        unset($this->callBacks[$data['token']]);
        unset($this->clients[$data['token']]);
        if ($callback != null) {
            call_user_func($callback, $data['result']);
        }
    }

    /**
     * @param $data
     * @return bool
     */
    public function shiftFromPool($data)
    {
        if ($this->pool->count() == 0) {//代表目前没有可用的连接
            $this->prepareOne();
            $this->commands->push($data);
            return false;
        } else {
            $client = $this->pool->shift();
            $this->clients[$data['token']] = $client;
            return $client;
        }
    }

    /**
     * 准备一个httpClient
     */
    public function prepareOne()
    {
        if ($this->client_count >= $this->client_max_count) {
            return;
        }
        $this->client_count++;
    }

    /**
     * @param $client
     */
    public function pushToPool($client)
    {
        if (!(isset($client->isclose) && $client->isclose)) {
            $this->pool->push($client);
        }
        if (count($this->commands) > 0) {//有残留的任务
            $command = $this->commands->shift();
            $this->execute($command);
        }
    }

    /**
     * 获取同步
     * @return mixed
     */
    abstract public function getSync();
}