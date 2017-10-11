<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-9-28
 * Time: 下午2:49
 */

namespace Server\Components\Middleware;


use Server\CoreBase\CoreBase;

abstract class Middleware extends CoreBase implements IMiddleware
{
    abstract public function before_handle();

    abstract public function after_handle($path);

    public function interrupt()
    {
        throw new \Exception('interrupt');
    }
}