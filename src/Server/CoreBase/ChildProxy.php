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
    /**
     * 设置上下文
     * @param $context
     */
    public function setContext(&$context)
    {
        $this->own->setContext($context);
    }
}