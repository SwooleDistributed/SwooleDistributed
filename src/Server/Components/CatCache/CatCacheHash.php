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

    protected $delimiter;

    protected $container = [];

    public function __construct(CatCacheProcess $process, $delimiter)
    {
        $this->process = $process;
        $this->delimiter = $delimiter;
    }

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
        $path = explode($this->delimiter, $offset);
        $deep = &$this->container;
        $count = count($path);
        for ($i = 0; $i < $count; $i++) {
            $point = $path[$i];
            if (array_key_exists($point, $deep)) {
                $deep = &$deep[$point];
            } else {
                return false;
            }
        }
        return true;
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
        $path = explode($this->delimiter, $offset);
        $deep = &$this->container;
        $count = count($path);
        for ($i = 0; $i < $count; $i++) {
            $point = $path[$i];
            if (array_key_exists($point, $deep)) {
                $deep = &$deep[$point];
            } else {
                return null;
            }
        }
        return $deep;
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
        $this->_offsetSet($offset, $value, $this->delimiter);
        $this->process->writeLog("_offsetSet", [$offset, $value, $this->delimiter]);
    }

    public function _offsetSet($offset, $value, $delimiter = '.')
    {
        if (is_null($offset)) {
            $this->container[] = $value;
        } else {
            $path = explode($delimiter, $offset);
            $deep = &$this->container;
            $count = count($path) - 1;
            for ($i = 0; $i < $count; $i++) {
                $point = $path[$i];
                if (array_key_exists($point, $deep)) {
                    $deep = &$deep[$point];
                } else {
                    $deep[$point] = [];
                    $deep = &$deep[$point];
                }
            }
            $deep[$path[$count]] = $value;
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
        $this->_offsetUnset($offset, $this->delimiter);
        $this->process->writeLog("_offsetUnset", [$offset, $this->delimiter]);
    }

    public function _offsetUnset($offset, $delimiter = '.')
    {
        if ($offset == "@command:flushdb@") {
            $this->container = [];
            return;
        }
        $path = explode($delimiter, $offset);
        $deep = &$this->container;
        $count = count($path) - 1;
        for ($i = 0; $i < $count; $i++) {
            $point = $path[$i];
            if (array_key_exists($point, $deep)) {
                $deep = &$deep[$point];
            } else {
                return;
            }
        }
        unset($deep[$path[$count]]);
    }

    /**
     * @return array
     */
    public function &getContainer()
    {
        return $this->container;
    }
}