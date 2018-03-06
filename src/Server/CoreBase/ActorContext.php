<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-1-3
 * Time: 下午1:48
 */

namespace Server\CoreBase;


use Server\Components\CatCache\CatCacheRpcProxy;

class ActorContext implements \ArrayAccess
{
    const CLASS_KEY = "@class";
    const WORKER_ID_KEY = "@workerId";
    const START_TIME = "@startTime";
    protected $data;
    protected $path;

    /**
     * @param $path
     * @param $class
     * @param $workerId
     * @param array $data
     * @return $this
     */
    public function initialization($path, $class, $workerId, $data = [])
    {
        if (!empty($data)) {
            $this->data = $data;
        } else {
            $this->data[self::CLASS_KEY] = $class;
            $this->data[self::WORKER_ID_KEY] = $workerId;
            $this->data[self::START_TIME] = time();
        }
        $this->path = $path;
        return $this;
    }

    public function &getData()
    {
        return $this->data;
    }
    /**
     * @return mixed
     */
    public function getClass()
    {
        return $this->data[self::CLASS_KEY];
    }

    /**
     * @return mixed
     */
    public function getWorkerId()
    {
        return $this->data[self::WORKER_ID_KEY];
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
        return isset($this->data[$offset]);
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
        return $this->data[$offset] ?? null;
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
        $this->data[$offset] = $value;
        $this->save();
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
        unset($this->data[$offset]);
        $this->save();
    }

    /**
     * 保存
     */
    public function save()
    {
        CatCacheRpcProxy::getRpc()[$this->path] = $this->data;
    }
    /**
     * 销毁
     */
    public function destroy()
    {
        unset(CatCacheRpcProxy::getRpc()[$this->path]);
        $this->data = [];
    }
}