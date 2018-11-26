<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-10
 * Time: 下午5:58
 */


//是否启动集群模式
$config['dispatch']['enable'] = false;
//dispatch间的心跳时间
$config['dispatch']['heart_time'] = 60;
$config['dispatch']['dispatch_udp_port'] = 9991;
$config['dispatch']['dispatch_port'] = 9992;
/**
 * dispatch服务器设置
 */
$config['dispatch_server']['socket'] = '0.0.0.0';
//接收udp广播的端口
$config['dispatch_server']['port'] = 60000;
$config['dispatch_server']['password'] = 'Hello Dispatch2';
$config['dispatch_server']['set'] = [
    'reactor_num' => 2, //reactor thread num
    'worker_num' => 4,    //worker process num
    'backlog' => 128,   //listen backlog
    'open_tcp_nodelay' => 1,
    'dispatch_mode' => 3,
    'enable_reuse_port' => true,
];
//这里如果设置了ip那么代表不会广播搜索了,如果不在一个内网则需要手动填写dispatch服务器地址
//$config['dispatch_server']['dispatch_servers']=['127.0.0.1'];
return $config;