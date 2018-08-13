<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-12-19
 * Time: 上午11:55
 */

namespace Server\Components\CatCache;

use Server\Components\Event\EventDispatcher;

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

    public function start()
    {
        \swoole_timer_tick(1000, function () {
            $timer_back = $this->map['timer_back'] ?? [];
            ksort($timer_back);
            $time = time();
            foreach ($timer_back as $key => $value) {
                if ($key > $time) break;
                foreach ($value as $mkey=>$mvalue) {
                    $mvalue['param_arr'][] = "timer_back.$key.$mkey";
                    EventDispatcher::getInstance()->randomDispatch(TimerCallBack::KEY, $mvalue);
                }
            }
        });
    }

    /**
     * 完成回调
     * @param $key
     */
    public function ackTimerCallBack($key)
    {
        unset($this->map[$key]);
    }

    /**
     * @param $time
     * @param $data
     * @return string
     */
    public function setTimerCallBack($time, $data)
    {
        $time_back_arr = $this->map->getContainer()["timer_back"]??[];
        $count = count($time_back_arr[$time]??[]);
        $key = "timer_back.$time.$count";
        $this->map[$key] = $data;
        return $key;
    }

    /**
     * @return array
     */
    public function getAll()
    {
        return $this->map->getContainer();
    }

    /**
     * 获取键
     * @param $path
     * @return bool|array
     */
    public function getKeys($path)
    {
        if (empty($path)) {
            return array_keys($this->map->getContainer());
        }
        $value = $this->map[$path];
        if (is_array($value)) {
            return array_keys($value);
        } else {
            return false;
        }

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
     * @return CatCacheRpc
     */
    public static function getRpc()
    {
        if (self::$rpc == null) {
            self::$rpc = new CatCacheRpc();
        }
        return self::$rpc;
    }
}
