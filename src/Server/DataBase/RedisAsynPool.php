<?php
/**
 * redis 异步客户端连接池
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-22
 * Time: 上午10:19
 */

namespace Server\DataBase;


use Server\CoreBase\SwooleException;
use Server\SwooleMarco;

class RedisAsynPool extends AsynPool
{
    const AsynName = 'redis';
    /**
     * 连接
     * @var array
     */
    public $connect;
    protected $redis_max_count = 0;
    private $active;

    public function __construct($connect = null)
    {
        parent::__construct();
        $this->connect = $connect;
    }

    public function server_init($swoole_server, $asyn_manager)
    {
        parent::server_init($swoole_server, $asyn_manager);
        $this->active = $this->config['redis']['active'];
    }

    /**
     * 映射redis方法
     * @param $name
     * @param $arguments
     */
    public function __call($name, $arguments)
    {
        $callback = array_pop($arguments);
        $data = [
            'name' => $name,
            'arguments' => $arguments
        ];
        $data['token'] = $this->addTokenCallback($callback);
        //写入管道
        $this->asyn_manager->writePipe($this, $data, $this->worker_id);
    }

    /**
     * 协程模式
     * @param $name
     * @param $arg
     * @return RedisCoroutine
     */
    public function coroutineSend($name, ...$arg)
    {
        if (get_instance()->isTaskWorker()) {//如果是task进程自动转换为同步模式
            return call_user_func_array([get_instance()->getRedis(), $name], $arg);
        } else {
            return new RedisCoroutine($this, $name, $arg);
        }
    }

    /**
     * 执行redis命令
     * @param $data
     */
    public function execute($data)
    {
        if (count($this->pool) == 0) {//代表目前没有可用的连接
            $this->prepareOne();
            $this->commands->push($data);
        } else {
            $client = $this->pool->shift();
            if($client->isClose){
                $this->reconnect($client);
                $this->commands->push($data);
                return;
            }
            $arguments = $data['arguments'];
            //特别处理下M命令(批量)
            switch (strtolower($data['name'])) {
                case 'srem':
                case 'sadd':
                    $key = $arguments[0];
                    if (is_array($arguments[1])) {
                        $arguments = $arguments[1];
                    }
                    array_unshift($arguments, $key);
                    break;
                case 'del':
                case 'delete':
                    if (is_array($arguments[0])) {
                        $arguments = $arguments[0];
                    }
                    break;
                case 'mset':
                    $harray = $arguments[0];
                    unset($arguments[0]);
                    foreach ($harray as $key => $value) {
                        $arguments[] = $key;
                        $arguments[] = $value;
                    }
                    $data['arguments'] = $arguments;
                    $data['M'] = $harray;
                    break;
                case 'hmset':
                    $harray = $arguments[1];
                    unset($arguments[1]);
                    foreach ($harray as $key => $value) {
                        $arguments[] = $key;
                        $arguments[] = $value;
                    }
                    $data['arguments'] = $arguments;
                    $data['M'] = $harray;
                    break;
                case 'mget':
                    $harray = $arguments[0];
                    unset($arguments[0]);
                    $arguments = array_merge($arguments, $harray);
                    $data['arguments'] = $arguments;
                    $data['M'] = $harray;
                    break;
                case 'hmget':
                    $harray = $arguments[1];
                    unset($arguments[1]);
                    $arguments = array_merge($arguments, $harray);
                    $data['arguments'] = $arguments;
                    $data['M'] = $harray;
                    break;
            }
            $arguments[] = function ($client, $result) use ($data) {
                switch (strtolower($data['name'])) {
                    case 'hmget':
                    case 'mget':
                        $data['result'] = [];
                        $count = count($result);
                        for ($i = 0; $i < $count; $i++) {
                            $data['result'][$data['M'][$i]] = $result[$i];
                        }
                        break;
                    case 'hgetall':
                        $data['result'] = [];
                        $count = count($result);
                        for ($i = 0; $i < $count; $i = $i + 2) {
                            $data['result'][$result[$i]] = $result[$i + 1];
                        }
                        break;
                    default:
                        $data['result'] = $result;
                }
                unset($data['M']);
                unset($data['arguments']);
                unset($data['name']);
                //给worker发消息
                $this->asyn_manager->sendMessageToWorker($this, $data);
                //回归连接
                $this->pushToPool($client);
            };
            $client->__call($data['name'], array_values($arguments));
        }
    }

    /**
     * 准备一个redis
     */
    public function prepareOne()
    {
        if ($this->redis_max_count + $this->waitConnetNum >= $this->config->get('redis.asyn_max_count', 10)) {
            return;
        }
        $this->prepareLock = true;
        $this->reconnect();
    }

    /**
     * 重连或者连接
     * @param null $client
     */
    public function reconnect($client = null)
    {
        $this->waitConnetNum++;
        if ($client == null) {
            $client = new \swoole_redis();
        }
        $callback = function ($client, $result) {
            $this->waitConnetNum--;
            if (!$result) {
                throw new SwooleException($client->errMsg);
            }
            if ($this->config->has('redis.' . $this->active . '.password')) {//存在验证
                $client->auth($this->config['redis'][$this->active]['password'], function ($client, $result) {
                    if (!$result) {
                        $errMsg = $client->errMsg;
                        unset($client);
                        throw new SwooleException($errMsg);
                    }
                    if ($this->config->has('redis.' . $this->active . '.select')) {//存在select
                        $client->select($this->config['redis'][$this->active]['select'], function ($client, $result) {
                            if (!$result) {
                                throw new SwooleException($client->errMsg);
                            }
                            $client->isClose = false;
                            if (!isset($client->client_id)) {
                                $client->client_id = $this->redis_max_count;
                                $this->redis_max_count++;
                            }
                            $this->pushToPool($client);
                        });
                    } else {
                        $client->isClose = false;
                        if (!isset($client->client_id)) {
                            $client->client_id = $this->redis_max_count;
                            $this->redis_max_count++;
                        }
                        $this->pushToPool($client);
                    }
                });
            } else {
                if ($this->config->has('redis.' . $this->active . '.select')) {//存在select
                    $client->select($this->config['redis'][$this->active]['select'], function ($client, $result) {
                        if (!$result) {
                            throw new SwooleException($client->errMsg);
                        }
                        $client->isClose = false;
                        if (!isset($client->client_id)) {
                            $client->client_id = $this->redis_max_count;
                            $this->redis_max_count++;
                        }
                        $this->pushToPool($client);
                    });
                } else {
                    $client->isClose = false;
                    if (!isset($client->client_id)) {
                        $client->client_id = $this->redis_max_count;
                        $this->redis_max_count++;
                    }
                    $this->pushToPool($client);
                }
            }
        };

        if ($this->connect == null) {
            $this->connect = [$this->config['redis'][$this->active]['ip'], $this->config['redis'][$this->active]['port']];
        }
        $client->on('Close',[$this,'onClose']);
        $client->connect($this->connect[0], $this->connect[1], $callback);
    }

    /**
     * 断开链接
     * @param $client
     */
    public function onClose($client){
        $client->isClose = true;
    }

    /**
     * @return string
     */
    public function getAsynName()
    {
        return self::AsynName;
    }

    /**
     * @return int
     */
    public function getMessageType()
    {
        return SwooleMarco::MSG_TYPE_REDIS_MESSAGE;
    }
}