<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-10-12
 * Time: 下午7:17
 */

namespace Server\Components\AOP;


abstract class Proxy implements IProxy
{
    /**
     * @var mixed
     */
    protected $own;

    public function __construct($own)
    {
        $this->own = $own;
    }

    public abstract function beforeCall($name, $arguments = null);

    public abstract function afterCall($name, $arguments = null);

    public function __call($name, $arguments)
    {
        $this->beforeCall($name, $arguments);
        $result = \co::call_user_func_array([$this->own, $name], $arguments);
        $this->afterCall($name, $arguments);
        return $result;
    }

    public function __set($name, $value)
    {
        $this->own->$name = $value;
    }

    public function __get($name)
    {
        return $this->own->$name;
    }

    public function getOwn()
    {
        return $this->own;
    }
}