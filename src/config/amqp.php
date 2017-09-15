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
$config['amqp']['active'] = 'local';

/**
 * 本地环境
 */
$config['amqp']['local']['host'] = 'localhost';
$config['amqp']['local']['port'] = 5672;
$config['amqp']['local']['user'] = 'guest';
$config['amqp']['local']['password'] = 'guest';
$config['amqp']['local']['vhost'] = '/';

/**
 * 最终的返回，固定写这里
 */
return $config;
