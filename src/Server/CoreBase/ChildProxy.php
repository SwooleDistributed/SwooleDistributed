<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-10-12
 * Time: 下午7:17
 */

namespace Server\CoreBase;


use Server\Components\AOP\Proxy;

class ChildProxy extends Proxy
{
    protected $class_name;

    public function __construct($own)
    {
        parent::__construct($own);
        $this->class_name = get_class($own);
    }

    /**
     * 设置上下文
     * @param $context
     */
    public function setContext(&$context)
    {
        $this->own->setContext($context);
    }

    public function beforeCall($name, $arguments = null)
    {
        $this->own->getContext()['RunStack'][] = $this->class_name . "::" . $name;
    }

    public function afterCall($name, $arguments = null)
    {
        // TODO: Implement afterCall() method.
    }
}