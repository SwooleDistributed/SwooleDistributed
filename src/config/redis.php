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
$config['redis']['active'] = 'local';

/**
 * 本地环境
 */
$config['redis']['local']['ip'] = '192.168.8.48';
$config['redis']['local']['port'] = 6379;
$config['redis']['local']['select'] = 1;
$config['redis']['local']['password'] = '123456';

/**
 * 这个不要删除，dispatch使用的redis环境
 * dispatch使用的环境
 */
$config['redis']['dispatch']['ip'] = 'unix:/var/run/redis/redis.sock';
$config['redis']['dispatch']['port'] = 0;
$config['redis']['dispatch']['select'] = 1;
$config['redis']['dispatch']['password'] = '123456';

$config['redis']['asyn_max_count'] = 10;

/**
 * 最终的返回，固定写这里
 */
return $config;
