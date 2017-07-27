<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-14
 * Time: 下午1:58
 */

/**
 * 服务器设置
 */
$config['server']['send_use_task_num'] = 20;
$config['server']['set'] = [
    'log_file' => LOG_DIR."/swoole.log",
    'log_level' => 5,
    'reactor_num' => 2, //reactor thread num
    'worker_num' => 4,    //worker process num
    'backlog' => 128,   //listen backlog
    'open_tcp_nodelay' => 1,
    'dispatch_mode' => 5,
    'task_worker_num' => 5,
    'task_max_request' => 5000,
    'enable_reuse_port' => true,
    'heartbeat_idle_time' => 120,//2分钟后没消息自动释放连接
    'heartbeat_check_interval' => 60,//1分钟检测一次
];

//协程超时时间
$config['coroution']['timerOut'] = 5000;

//是否启用自动reload
$config['auto_reload_enable'] = true;
return $config;