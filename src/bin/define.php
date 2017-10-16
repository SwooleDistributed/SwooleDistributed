<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-6-28
 * Time: 上午9:54
 */

define("BIN_DIR", __DIR__);
define('MYROOT',BIN_DIR."/..");

require_once MYROOT . '/vendor/autoload.php';

checkfile("SERVER_DIR", MYROOT."/vendor/tmtbe/swooledistributed/src/Server");
checkfile("APP_DIR", MYROOT . "/src/app");
checkfile("WWW_DIR", MYROOT . "/src/www");
checkfile("LUA_DIR", MYROOT . "/src/lua");
checkfile("CONFIG_DIR", MYROOT . "/src/config");
checkfile("TEST_DIR", MYROOT . "/src/test");
checkfile("LOG_DIR", BIN_DIR . "/log");
checkfile("PID_DIR", BIN_DIR . "/pid");

function checkfile($name,$path)
{
    define($name,$path);
    if(!file_exists($path)){
        mkdir($path);
    }
}