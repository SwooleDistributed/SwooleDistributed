<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-14
 * Time: 下午1:58
 */

/**
 * 选择数据库环境
 */
$config['redis']['active'] = 'test';
/**
 * 本地环境
 */
$config['redis']['test']['ip'] = 'localhost';
$config['redis']['test']['port'] = 6379;
$config['redis']['test']['select'] = 5;
$config['redis']['test']['password'] = '123456';
$config['redis']['asyn_max_count'] = 10;

/**
 * 最终的返回，固定写这里
 */
return $config;