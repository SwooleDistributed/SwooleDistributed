<?php
/**
 * 异步连接池基类
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-25
 * Time: 上午11:40
 */

namespace Server\Asyn;


use Noodlehaus\Config;
use Server\Coroutine\CoroutineChangeToken;

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
    protected $token = 1;
    protected $client_max_count;
    protected $client_count;
    private $isDestroy;

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
     * 迁移工作,将别的相同pool的命令迁移到此处
     * @param $migrate
     */
    public function migrates($migrate)
    {
        $token = $this->addTokenCallback($migrate['callback']);
        call_user_func($migrate['callback'],new CoroutineChangeToken($token));
        unset($migrate['callback']);
        $migrate['token'] = $token;
        $this->execute($migrate);
    }

    /**
     * 移除token回调
     * @param $token
     */
    public function removeTokenCallback($token)
    {
        unset($this->callBacks[$token]);
    }

    /**
     * 超时时需要处理下
     * 销毁垃圾
     * @param $token
     */
    public function destoryGarbage($token)
    {
        unset($this->callBacks[$token]);
        if ($this->clients!=null&&array_key_exists($token, $this->clients)) {
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
        $token = $data['token'];
        $callback = $this->callBacks[$token]??null;
        unset($this->callBacks[$token]);
        unset($this->clients[$token]);
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
            return false;
        }
        $this->client_count++;
        return true;
    }

    /**
     * @param $client
     */
    public function pushToPool($client)
    {
        if($this->isDestroy){
            $this->destoryClient($client);
            return;
        }
        $this->pool->push($client);
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

    /**
     * 销毁,返回需要迁移的命令
     * @param array $migrate
     * @return array
     */
    public function destroy(&$migrate = []){
        $this->isDestroy = true;
        foreach ($this->pool as $client){
            $this->destoryClient($client);
        }
        foreach ($this->commands as $command){
            $command['callback'] = $this->callBacks[$command['token']];
            $migrate[] = $command;
        }
        $this->callBacks = null;
        $this->clients = null;
        $this->pool = null;
        $this->commands = null;
        $this->config = null;
        return $migrate;
    }
}