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
$config['redis']['local']['ip'] = '192.168.8.57';
$config['redis']['local']['port'] = 6379;
$config['redis']['local']['select'] = 1;
$config['redis']['local']['password'] = '123456';

/**
 * 本地环境2
 */
$config['redis']['local2']['ip'] = 'localhost';
$config['redis']['local2']['port'] = 6379;
$config['redis']['local2']['select'] = 2;
$config['redis']['local2']['password'] = '123456';

$config['redis']['asyn_max_count'] = 10;
/**
 * 最终的返回，固定写这里
 */
return $config;
