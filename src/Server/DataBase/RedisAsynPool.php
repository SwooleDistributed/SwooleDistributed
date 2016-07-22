<?php
/**
 * redis 异步客户端连接池
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-22
 * Time: 上午10:19
 */

namespace Server\DataBase;


use Noodlehaus\Config;
use Server\CoreBase\SwooleException;
use Server\SwooleMarco;
use Server\SwooleServer;

class RedisAsynPool
{
    protected $token = 0;
    protected $pool = [];
    protected $commands = [];
    protected $callBacks = [];
    protected $redis_max_count = 0;
    protected $process;
    protected $worker_id;
    protected $server;
    protected $swoole_server;
    /**
     * @var Config
     */
    protected $config;

    /**
     * 作为服务的初始化
     * @param $config
     * @param $swoole_server SwooleServer
     * @param $process
     */
    public function server_init($config, $swoole_server, $process)
    {
        $this->config = $config;
        $this->process = $process;
        $this->swoole_server = $swoole_server;
        $this->server = $swoole_server->server;
        swoole_event_add($process->pipe, [$this, 'getPipeMessage']);
    }

    /**
     * 作为客户端的初始化
     * @param $worker_id
     */
    public function worker_init($process,$worker_id)
    {
        $this->process = $process;
        $this->worker_id = $worker_id;
    }
    /**
     * 管道来消息了（向redispool进程发送消息）
     * @param $pipe
     */
    public function getPipeMessage($pipe)
    {
        $data = unserialize($this->process->read());
        $this->execute($data);
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
            'worker_id'=>$this->worker_id,
            'token' => $this->token,
            'name' => $name,
            'arguments' => $arguments
        ];
        $this->callBacks[$data['token']] = $callback;
        $this->token++;
        if ($this->token > 65536) {
            $this->token = 0;
        }
        //写入管道
        $this->process->write(serialize($data));
    }

    /**
     * 分发回调
     * @param $data
     */
    public function _distribute($data)
    {
        $callback = $this->callBacks[$data['token']];
        if($callback!=null) {
            call_user_func($callback, $data['result']);
        }
    }

    /**
     * 执行redis命令
     * @param $data
     */
    private function execute($data)
    {
        $client = array_pop($this->pool);
        if ($client == null) {//代表目前没有可用的连接
            $this->prepareOneRedis();
            $this->commands[] = $data;
        } else {
            $arguments = $data['arguments'];
            //特别处理下M命令(批量)
            $harray = $arguments[1]??null;
            if ($harray != null && is_array($harray)) {
                unset($arguments[1]);
                $arguments = array_merge($arguments, $harray);
                $data['arguments'] = $arguments;
                $data['M'] = $harray;
            }
            $arguments[] = function ($client, $result) use ($data) {
                if (key_exists('M', $data)) {//批量命令
                    $data['result'] = [];
                    for ($i = 0; $i < count($result); $i++) {
                        $data['result'][$data['M'][$i]] = $result[$i];
                    }
                } else {
                    $data['result'] = $result;
                }
                unset($data['M']);
                unset($data['arguments']);
                unset($data['name']);
                //写入管道
                $message = $this->swoole_server->packSerevrMessageBody(SwooleMarco::MSG_TYPE_REDIS_MESSAGE, $data);
                $this->server->sendMessage($message,$data['worker_id']);
                //回归连接
                $this->pushToPool($client);
            };
            $client->__call($data['name'], $arguments);
        }
    }

    /**
     * 归还一个redis连接
     * @param $client
     */
    protected function pushToPool($client)
    {
        $this->pool[] = $client;
        if (count($this->commands) > 0) {//有残留的任务
            $command = array_pop($this->commands);
            $this->execute($command);
        }
    }

    /**
     * 准备一个redis
     */
    protected function prepareOneRedis()
    {
        if ($this->redis_max_count > $this->config->get('redis.asyn_max_count', 10)) {
            return;
        }
        $client = new \swoole_redis();
        $client->connect($this->config['redis']['ip'], $this->config['redis']['port'], function ($client, $result) {
            if (!$result) {
                throw new SwooleException($client->errMsg);
            }
            $client->auth($this->config['redis']['password'], function ($client, $result) {
                if (!$result) {
                    throw new SwooleException($client->errMsg);
                }
                $client->select($this->config['redis']['select'], function ($client, $result) {
                    if (!$result) {
                        throw new SwooleException($client->errMsg);
                    }
                    $this->redis_max_count++;
                    $this->pushToPool($client);
                });
            });
        });
    }
}