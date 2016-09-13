<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\DataBase;


use Server\CoreBase\CoroutineNull;
use Server\CoreBase\ICoroutineBase;

class RedisCoroutine implements ICoroutineBase
{
    /**
     * @var RedisAsynPool
     */
    public $redisAsynPool;
    public $name;
    public $arguments;
    public $result;

    public function __construct($redisAsynPool, $name, $arguments)
    {
        $this->result = CoroutineNull::getInstance();
        $this->redisAsynPool = $redisAsynPool;
        $this->name = $name;
        $this->arguments = $arguments;
        $this->send(function ($result) {
            $this->result = $result;
        });
    }

    public function send($callback)
    {
        $this->arguments[] = $callback;
        $this->redisAsynPool->__call($this->name, $this->arguments);
    }

    public function getResult()
    {
        return $this->result;
    }
}

class RedisNull
{

}