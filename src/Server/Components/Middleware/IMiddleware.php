<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-9-28
 * Time: 上午11:37
 */

namespace Server\Components\Middleware;

interface IMiddleware
{
    function setContext(&$context);

    function before_handle();

    function after_handle($path);
}