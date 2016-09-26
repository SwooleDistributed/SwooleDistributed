<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-14
 * Time: 下午1:58
 */
/**
 * timerTask定时任务
 * （必填）task名称 task_name
 * （必填）执行task的方法 method_name
 * （选填）执行区间 [start_time,end_time) 格式： Y-m-d H:i:s 没有代表一直执行
 * （必填）执行间隔 interval_time 单位： 秒
 * （选填）最大执行次数 max_exec，默认不限次数
 */
//dispatch发现广播，实现集群的实现
$config['timerTask'][] = [
    'task_name' => 'UdpDispatchTask',
    'method_name' => 'send',
    'interval_time' => '30'
];
return $config;