<?php
/**
 * 面前切片aspect
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-14
 * Time: 下午1:58
 */

$config['aop'][] =
    [
        "aspect_class" => "Server\Aspects\RunAspect",
        "pointcut" => "*::*",
        "before_method" => "before",
        "after_method" => "after",
    ];
return $config;