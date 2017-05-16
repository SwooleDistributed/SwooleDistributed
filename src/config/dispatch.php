<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-10
 * Time: 下午5:58
 */


//是否启动集群模式
$config['use_dispatch'] = true;
//dispatch间的心跳时间
$config['dispatch_heart_time'] = 60;

/**
 * dispatch服务器设置
 */
$config['dispatch_server']['socket'] = '0.0.0.0';
$config['dispatch_server']['port'] = 60000;
$config['dispatch_server']['password'] = 'Hello Dispatch';
$config['dispatch_server']['set'] = [
    'reactor_num' => 2, //reactor thread num
    'worker_num' => 4,    //worker process num
    'backlog' => 128,   //listen backlog
    'open_tcp_nodelay' => 1,
    'dispatch_mode' => 3,
    'enable_reuse_port' => true,
];
//这里如果设置了ip那么代表不会广播搜索了,如果不在一个内网则需要手动填写dispatch服务器地址
$config['dispatch_server']['dispatch_servers']=['127.0.0.1'];
return $config;