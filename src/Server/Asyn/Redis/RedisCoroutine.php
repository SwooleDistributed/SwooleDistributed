<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\Asyn\Redis;

use Server\Coroutine\CoroutineBase;
use Server\Memory\Pool;
use Server\Start;

class RedisCoroutine extends CoroutineBase
{
    /**
     * @var RedisAsynPool
     */
    public $redisAsynPool;
    public $name;
    public $arguments;
    /**
     * 对象池模式用来代替__construct
     * @param $redisAsynPool
     * @param $name
     * @param $arguments
     * @param $set
     * @return int
     */
    public function init($redisAsynPool, $name, $arguments, $set)
    {
        $this->redisAsynPool = $redisAsynPool;
        $this->name = $name;
        $this->arguments = $arguments;
        $this->set($set);
        $d = "[$name ".implode(" ",$arguments)."]";
        $this->request = "[redis]$d";
        if (Start::getDebug()){
            secho("REDIS",$d);
        }
        return $this->send(null);
    }

    public function send($callback)
    {
        return $this->redisAsynPool->__call($this->name, $this->arguments);
    }

    public function destroy()
    {
        parent::destroy();
        $this->token = null;
        $this->redisAsynPool = null;
        $this->name = null;
        $this->arguments = null;
        Pool::getInstance()->push($this);
    }
}