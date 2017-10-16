<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-10-12
 * Time: 下午7:27
 */

namespace Server\Components\AOP;


class AOP implements IAOP
{
    /**
     * @var Proxy
     */
    protected $proxy;

    public function __construct($proxy = Proxy::class)
    {
        $this->proxy = new $proxy($this);
    }

    /**
     * 获得代理
     * @return Proxy
     */
    public function getProxy()
    {
        return $this->proxy;
    }

    /**
     * @param IAOP $object
     * @return mixed
     */
    public static function getAOP(IAOP $object)
    {
        return $object->getProxy();
    }
}