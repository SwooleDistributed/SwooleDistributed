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

class RedisCoroutine extends CoroutineBase
{
    /**
     * @var RedisAsynPool
     */
    public $redisAsynPool;
    public $name;
    public $arguments;

    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 对象池模式用来代替__construct
     * @param $redisAsynPool
     * @param $name
     * @param $arguments
     * @return $this
     */
    public function init($redisAsynPool, $name, $arguments)
    {
        $this->redisAsynPool = $redisAsynPool;
        $this->name = $name;
        $this->arguments = $arguments;
        $this->request = "#redis: $name";
        $this->send(function ($result) {
            $this->result = $result;
            $this->immediateExecution();
        });
        return $this;
    }
    public function send($callback)
    {
        $this->arguments[] = $callback;
        $this->token = $this->redisAsynPool->__call($this->name, $this->arguments);
    }

    public function destroy()
    {
        parent::destroy();
        $this->redisAsynPool->removeTokenCallback($this->token);
        $this->token = null;
        $this->redisAsynPool = null;
        $this->name = null;
        $this->arguments = null;
        Pool::getInstance()->push($this);
    }

    protected function onTimerOutHandle()
    {
        parent::onTimerOutHandle();
        $this->redisAsynPool->destoryGarbage($this->token);
    }
}