<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-10-16
 * Time: 上午11:47
 */

namespace Server\Components\AOP;


interface IProxy
{
    function beforeCall($name, $arguments);

    function afterCall($name, $arguments);
}