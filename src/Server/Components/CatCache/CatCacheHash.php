<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-12-18
 * Time: 下午6:08
 */

namespace Server\Components\CatCache;


class CatCacheHash implements \ArrayAccess
{
    /**
     * @var CatCacheProcess
     */
    protected $process;

    public function __construct(CatCacheProcess $process)
    {
        $this->process = $process;
    }

    private $container = [];

    /**
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     * @since 5.0.0
     */
    public function offsetExists($offset)
    {
        return isset($this->container[$offset]);
    }

    /**
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     * @since 5.0.0
     */
    public function offsetGet($offset)
    {
        return isset($this->container[$offset]) ? $this->container[$offset] : null;
    }

    /**
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetSet($offset, $value)
    {
        $this->_offsetSet($offset, $value);
        $this->process->writeLog("_offsetSet", [$offset, $value]);
    }

    public function _offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $this->container[$offset] = $value;
        }
    }

    /**
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     * @since 5.0.0
     */
    public function offsetUnset($offset)
    {
        $this->_offsetUnset($offset);
        $this->process->writeLog("_offsetUnset", [$offset]);
    }

    public function _offsetUnset($offset)
    {
        unset($this->container[$offset]);
    }

    /**
     * @return array
     */
    public function &getContainer()
    {
        return $this->container;
    }
}