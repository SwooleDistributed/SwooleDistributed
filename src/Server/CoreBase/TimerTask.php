<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-2-24
 * Time: 下午1:16
 */

namespace Server\CoreBase;


use Server\Coroutine\Coroutine;

class TimerTask extends CoreBase
{
    protected $timer_tasks_used;

    public function __construct($config)
    {
        parent::__construct();
        $timer_tasks = $config->get('timerTask');
        $this->timer_tasks_used = [];

        foreach ($timer_tasks as $timer_task) {
            $task_name = $timer_task['task_name']??'';
            $model_name = $timer_task['model_name']??'';
            if (empty($task_name) && empty($model_name)) {
                throw new SwooleException('定时任务配置错误，缺少task_name或者model_name.');
            }
            $method_name = $timer_task['method_name'];
            if (!array_key_exists('start_time', $timer_task)) {
                $start_time = time();
            } else {
                $start_time = strtotime(date($timer_task['start_time']));
            }
            if (!array_key_exists('end_time', $timer_task)) {
                $end_time = -1;
            } else {
                $end_time = strtotime(date($timer_task['end_time']));
            }
            if (!array_key_exists('delay', $timer_task)) {
                $delay = false;
            } else {
                $delay = $timer_task['delay'];
            }
            $interval_time = $timer_task['interval_time'] < 1 ? 1 : $timer_task['interval_time'];
            $max_exec = $timer_task['max_exec']??-1;
            $this->timer_tasks_used[] = [
                'task_name' => $task_name,
                'model_name' => $model_name,
                'method_name' => $method_name,
                'start_time' => $start_time,
                'next_time' => $start_time,
                'end_time' => $end_time,
                'interval_time' => $interval_time,
                'max_exec' => $max_exec,
                'now_exec' => 0,
                'delay' => $delay
            ];
        }
        $this->timerTask();
        swoole_timer_tick(1000, function () {
            $this->timerTask();
        });
    }

    /**
     * 定时任务
     */
    public function timerTask()
    {
        $time = time();
        foreach ($this->timer_tasks_used as &$timer_task) {
            if ($timer_task['next_time'] < $time) {
                $count = round(($time - $timer_task['start_time']) / $timer_task['interval_time']);
                $timer_task['next_time'] = $timer_task['start_time'] + $count * $timer_task['interval_time'];
            }
            if ($timer_task['end_time'] != -1 && $time > $timer_task['end_time']) {//说明执行完了一轮，开始下一轮的初始化
                $timer_task['end_time'] += 86400;
                $timer_task['start_time'] += 86400;
                $timer_task['next_time'] = $timer_task['start_time'];
                $timer_task['now_exec'] = 0;
            }
            if (($time == $timer_task['next_time']) &&
                ($time < $timer_task['end_time'] || $timer_task['end_time'] == -1) &&
                ($timer_task['now_exec'] < $timer_task['max_exec'] || $timer_task['max_exec'] == -1)
            ) {
                if ($timer_task['delay']) {
                    $timer_task['next_time'] += $timer_task['interval_time'];
                    $timer_task['delay'] = false;
                    continue;
                }
                $timer_task['now_exec']++;
                $timer_task['next_time'] += $timer_task['interval_time'];
                if (!empty($timer_task['task_name'])) {
                    $task = $this->loader->task($timer_task['task_name'], $this);
                    call_user_func([$task, $timer_task['method_name']]);
                    $task->startTask(null);
                } else {
                    $model = $this->loader->model($timer_task['model_name'], $this);
                    Coroutine::startCoroutine([$model, $timer_task['method_name']]);
                }
            }
        }
    }
}