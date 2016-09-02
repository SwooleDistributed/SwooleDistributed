<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-14
 * Time: 下午1:58
 */

$config['redis']['ip'] = 'localhost';
$config['redis']['port'] = 6379;
$config['redis']['select'] = 1;
$config['redis']['password'] = '123456';
$config['redis']['asyn_max_count'] = 10;
return $config;