<?php
/**
 * redis 异步客户端连接池
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-22
 * Time: 上午10:19
 */

namespace Server\Asyn\Redis;


use Server\Asyn\IAsynPool;
use Server\CoreBase\SwooleException;
use Server\Memory\Pool;

class RedisAsynPool implements IAsynPool
{
    const AsynName = 'redis';
    /**
     * 连接
     * @var array
     */
    public $connect;
    private $active;
    private $redis_client;
    protected $name;
    protected $pool_chan;
    protected $config;
    private $client_max_count;

    public function __construct($config, $active)
    {
        $this->config = $config;
        $this->active = $active;
        $this->client_max_count = $this->config->get('redis.asyn_max_count', 10);
        if (get_instance()->isTaskWorker()) return;
        $this->pool_chan = new \chan($this->client_max_count);
        for ($i = 0; $i < $this->client_max_count; $i++) {
            $client = new \Redis();
            if ($client->connect($this->config['redis'][$this->active]['ip'], $this->config['redis'][$this->active]['port']) == false) {
                throw new SwooleException($client->getLastError());
            }
            if (!empty($this->config->get('redis.' . $this->active . '.password', ""))) {//存在验证
                if ($client->auth($this->config['redis'][$this->active]['password']) == false) {
                    throw new SwooleException($client->getLastError());
                }
            }
            if ($this->config->has('redis.' . $this->active . '.select')) {//存在select
                $client->select($this->config['redis'][$this->active]['select']);
            }
            $client->id = $i;
            $this->pushToPool($client);
        }
    }

    /**
     * 映射redis方法
     * @param $name
     * @param $arguments
     * @return int
     */
    public function __call($name, $arguments)
    {
        $data = [
            'name' => $name,
            'arguments' => $arguments
        ];
        return $this->execute($data);
    }

    /**
     * 执行redis命令
     * @param $data
     * @return mixed
     * @throws SwooleException
     */
    public function execute($data)
    {
        $client = $this->pool_chan->pop();
        try {
            $arguments = $data['arguments'];
            $data['result'] = call_user_func_array([$client, $data['name']], $arguments);
        } catch (\RedisException $e) {
            $this->reconnect($client);
            $this->commands->push($data);
        }
        //回归连接
        $this->pushToPool($client);
        return $data['result'];
    }

    public function pushToPool($client)
    {
        $this->pool_chan->push($client);
    }

    /**
     * 重连或者连接
     * @param null $client
     * @throws SwooleException
     */
    public function reconnect($client = null)
    {
        go(function () use ($client) {
            if ($client->connect($this->config['redis'][$this->active]['ip'], $this->config['redis'][$this->active]['port']) == false) {
                throw new SwooleException($client->getLastError());
            }
            if (!empty($this->config->get('redis.' . $this->active . '.password', ""))) {//存在验证
                if ($client->auth($this->config['redis'][$this->active]['password']) == false) {
                    throw new SwooleException($client->getLastError());
                }
            }
            if ($this->config->has('redis.' . $this->active . '.select')) {//存在select
                $client->select($this->config['redis'][$this->active]['select']);
            }
            $this->pushToPool($client);
        });
    }

    /**
     * 协程模式
     * @param $name
     * @param array ...$arg
     * @param callable $set
     * @return RedisCoroutine
     * @throws SwooleException
     */
    public function coroutineSend($name, $arg, callable $set = null)
    {
        if (get_instance()->isTaskWorker()) {//如果是task进程自动转换为同步模式
            try {
                $value = sd_call_user_func_array([$this->getSync(), $name], $arg);
            } catch (\RedisException $e) {
                $this->redis_client = null;
                $value = sd_call_user_func_array([$this->getSync(), $name], $arg);
            }
            return $value;
        } else {
            return Pool::getInstance()->get(RedisCoroutine::class)->init($this, $name, $arg, $set);
        }
    }

    /**
     * 获取同步
     * @return \Redis
     * @throws SwooleException
     */
    public function getSync()
    {
        if ($this->redis_client != null) return $this->redis_client;
        //同步redis连接，给task使用
        $this->redis_client = new \Redis();
        if ($this->redis_client->connect($this->config['redis'][$this->active]['ip'], $this->config['redis'][$this->active]['port']) == false) {
            throw new SwooleException($this->redis_client->getLastError());
            $this->redis_client = null;
        }
        if (!empty($this->config->get('redis.' . $this->active . '.password', ""))) {//存在验证
            if ($this->redis_client->auth($this->config['redis'][$this->active]['password']) == false) {
                throw new SwooleException($this->redis_client->getLastError());
                $this->redis_client = null;
            }
        }
        if ($this->config->has('redis.' . $this->active . '.select')) {//存在select
            $this->redis_client->select($this->config['redis'][$this->active]['select']);
        }
        return $this->redis_client;
    }

    /**
     * 协程模式 更加便捷
     * @return \Redis
     * @throws SwooleException
     */
    public function getCoroutine()
    {
        return Pool::getInstance()->get(CoroutineRedisHelp::class)->init($this);
    }

    /**
     * @return string
     */
    public function getAsynName()
    {
        return self::AsynName . ":" . $this->name;
    }

    /**
     * 销毁Client
     * @param \Redis $client
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