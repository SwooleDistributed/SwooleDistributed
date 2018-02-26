<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-5-24
 * Time: 下午4:30
 */

namespace Server\Asyn\Redis;

class RedisRoute
{
    protected static $instance;
    protected $route_map = [];

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new RedisRoute();
        }
        return self::$instance;
    }

    /**
     * 添加一个路由规则
     * @param $key
     * @param $redis_pool_name
     */
    public function addRedisPoolRoute($key, $redis_pool_name)
    {
        $this->route_map[$key] = $redis_pool_name;
    }

    /**
     * 批量添加路由规则
     * @param $config
     */
    public function addRedisPoolRoutes($config)
    {
        foreach ($config as $key => $value) {
            $this->route_map[$key] = $value;
        }
    }

    /**
     * @param $key
     * @return RedisAsynPool
     */
    protected function getRedisPoolFromKey($key)
    {
        $redis_pool_name = $this->route_map[$key] ?? 'redisPool';
        return get_instance()->getAsynPool($redis_pool_name);
    }

    /**
     * @param $name
     * @return \Server\Asyn\AsynPool
     */
    public function getRedisPool($name = 'redisPool')
    {
        return get_instance()->getAsynPool($name);
    }

    /**
     * 获取key
     * @param $name
     * @param $arguments
     * @return mixed
     */
    protected function getKey($name, $arguments)
    {
        if (empty($arguments)) {
            return null;
        }
        if (is_string($arguments[0])) {
            return $arguments[0];
        } else {
            return null;
        }
    }

    /**
     * 协程模式 更加便捷
     * @return \Redis
     */
    public function getCoroutine()
    {
        return $this;
    }

    public function coroutineSend($name, $arg, callable $set = null)
    {
        $redis_pool = $this->getRedisPoolFromKey($this->getKey($name, $arg));
        return $redis_pool->coroutineSend($name, $arg, $set);
    }

    public function __call($name, $arguments)
    {
        $redis_pool = $this->getRedisPoolFromKey($this->getKey($name, $arguments));
        return $redis_pool->coroutineSend($name, $arguments);
    }
}