<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-1-4
 * Time: 上午9:38
 */

namespace Server\Asyn\Redis;


use Server\Memory\Pool;

class CoroutineRedisHelp
{
    private $redisAsynPool;

    public function __construct($redisAsynPool)
    {
        $this->redisAsynPool = $redisAsynPool;
    }

    public function __call($name, $arguments)
    {
        if (get_instance()->isTaskWorker()) {//如果是task进程自动转换为同步模式
            return call_user_func_array([get_instance()->getRedis(), $name], $arguments);
        } else {
            return Pool::getInstance()->get(RedisCoroutine::class)->init($this->redisAsynPool, $name, $arguments);
        }
    }
}