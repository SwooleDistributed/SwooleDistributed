<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-12-20
 * Time: 下午6:19
 */

namespace Server\Components\CatCache;

use Server\Components\Process\ProcessManager;

class CatCacheRpc implements \ArrayAccess
{
    public function __call($name, $arguments)
    {
        return ProcessManager::getInstance()->getRpcCall(CatCacheProcess::class)->__call($name, $arguments);
    }

    public function offsetExists($offset)
    {
        return $this->__call("offsetExists", [$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->__call("offsetGet", [$offset]);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        $this->__call("offsetSet", [$offset, $value]);
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset)
    {
        $this->__call("offsetUnset", [$offset]);
    }

}