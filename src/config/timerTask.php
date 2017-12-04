<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-14
 * Time: 下午1:58
 */
/**
 * timerTask定时任务
 * （选填）task名称 task_name
 * （选填）model名称 model_name  task或者model必须有一个优先匹配task
 * （必填）执行task的方法 method_name
 * （选填）执行开始时间 start_time,end_time) 格式： Y-m-d H:i:s 没有代表一直执行,一旦end_time设置后会进入1天一轮回的模式
 * （必填）执行间隔 interval_time 单位： 秒
 * （选填）最大执行次数 max_exec，默认不限次数
 * （选填）是否立即执行 delay，默认为false立即执行
 */
$config['timerTask'] = [];
//下面例子表示在每天的14点到20点间每隔1秒执行一次
/*$config['timerTask'][] = [
    //'start_time' => 'Y-m-d 19:00:00',
    //'end_time' => 'Y-m-d 20:00:00',
    'task_name' => 'TestTask',
    'method_name' => 'test',
    'interval_time' => '1',
];*/
//下面例子表示在每天的14点到15点间每隔1秒执行一次，一共执行5次
/*$config['timerTask'][] = [
    'start_time' => 'Y-m-d 14:00:00',
    'end_time' => 'Y-m-d 15:00:00',
    'task_name' => 'TestTask',
    'method_name' => 'test',
    'interval_time' => '1',
    'max_exec' => 5,
];*/
//下面例子表示在每天的0点执行1次(间隔86400秒为1天)
/*$config['timerTask'][] = [
    'start_time' => 'Y-m-d 23:59:59',
    'task_name' => 'TestTask',
    'method_name' => 'test',
    'interval_time' => '86400',
];*/
//下面例子表示在每天的0点执行1次
/*$config['timerTask'][] = [
    'start_time' => 'Y-m-d 14:53:10',
    'end_time' => 'Y-m-d 14:54:11',
    'task_name' => 'TestTask',
    'method_name' => 'test',
    'interval_time' => '1',
    'max_exec' => 1,
];*/
return $config;