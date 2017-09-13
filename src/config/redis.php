<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-14
 * Time: 下午1:58
 */

/**
 * 选择数据库环境
 */
$config['redis']['enable'] = true;
$config['redis']['active'] = 'local';

/**
 * 本地环境
 */
$config['redis']['local']['ip'] = 'redis';
$config['redis']['local']['port'] = 6379;
$config['redis']['local']['select'] = 0;

$config['redis']['asyn_max_count'] = 10;
/**
 * 最终的返回，固定写这里
 */
return $config;
