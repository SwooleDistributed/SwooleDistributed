<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午4:49
 */
$config['database']['active'] = 'test';
$config['database']['test']['host'] = 'localhost';
$config['database']['test']['port'] = '3306';
$config['database']['test']['user'] = 'test';
$config['database']['test']['password'] = 'test';
$config['database']['test']['database'] = 'test';
$config['database']['test']['charset'] = 'utf8';
$config['database']['asyn_max_count'] = 10;
return $config;