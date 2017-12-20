<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-12-19
 * Time: 上午11:55
 */

namespace Server\Components\CatCache;

use Server\Components\Process\ProcessManager;

/**
 * 緩存的RPC代理
 * Class CatCacheRpcProxy
 * @package Server\Components\CatCache
 */
class CatCacheRpcProxy
{
    private static $rpc;
    /**
     * @var CatCacheHash
     */
    protected $map;

    public function setMap(&$map)
    {
        $this->map = $map;
    }


    /**
     * @param $key
     * @param $value
     * @oneWay
     */
    public function set($key, $value)
    {
        $this->map[$key] = $value;
    }

    /**
     * @param $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->map[$key] ?? null;
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return $this->map->getContainer();
    }

    /**
     * @return CatCacheRpcProxy
     */
    public static function getRpc()
    {
        if (self::$rpc == null) {
            self::$rpc = new CatCacheRpc();
        }
        return self::$rpc;
    }
}

class CatCacheRpc
{
    public function __call($name, $arguments)
    {
        return ProcessManager::getInstance()->getRpcCall(CatCacheProcess::class)->__call($name, $arguments);
    }
}