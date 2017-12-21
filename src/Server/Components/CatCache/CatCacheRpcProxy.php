<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-12-19
 * Time: 上午11:55
 */

namespace Server\Components\CatCache;

/**
 * 緩存的RPC代理
 * Class CatCacheRpcProxy
 * @package Server\Components\CatCache
 */
class CatCacheRpcProxy implements \ArrayAccess
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

    public function __call($name, $arguments)
    {
        return call_user_func_array([$this, $name], $arguments);
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return $this->map->getContainer();
    }

    /**
     * @param $offset
     * @return mixed
     */
    public function offsetExists($offset)
    {
        return $this->map->offsetExists($offset);
    }

    /**
     * @param $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->map->offsetGet($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @oneWay
     */
    public function offsetSet($offset, $value)
    {
        $this->map->offsetSet($offset, $value);
    }

    /**
     * @param mixed $offset
     * @oneWay
     */
    public function offsetUnset($offset)
    {
        $this->map->offsetUnset($offset);
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
