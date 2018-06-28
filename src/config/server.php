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
$config['name'] = 'SWD';
$config['server']['send_use_task_num'] = 500;
$config['server']['set'] = [
    'log_file' => LOG_DIR."/swoole.log",
    'pid_file' => PID_DIR . '/server.pid',
    'log_level' => 5,
    'reactor_num' => 4, //reactor thread num
    'worker_num' => 4,    //worker process num
    'backlog' => 128,   //listen backlog
    'open_tcp_nodelay' => 1,
    'socket_buffer_size' => 1024 * 1024 * 1024,
    'dispatch_mode' => 2,
    'task_worker_num' => 1,
    'task_max_request' => 5000,
    'enable_reuse_port' => true,
    'heartbeat_idle_time' => 120,//2分钟后没消息自动释放连接
    'heartbeat_check_interval' => 60,//1分钟检测一次
    'max_connection' => 100000
];
//协程超时时间
$config['coroution']['timerOut'] = 5000;

//是否启用自动reload
$config['auto_reload_enable'] = false;

//是否允许访问Server中的Controller，如果不允许将禁止调用Server包中的Controller
$config['allow_ServerController'] = true;
//是否允许监控流量数据
$config['allow_MonitorFlowData'] = true;
return $config;