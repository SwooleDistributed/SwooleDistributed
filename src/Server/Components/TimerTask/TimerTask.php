<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-2-24
 * Time: 下午1:16
 */

namespace Server\Components\TimerTask;


use Server\Asyn\HttpClient\HttpClient;
use Server\Asyn\HttpClient\HttpClientPool;
use Server\Components\Event\Event;
use Server\Components\Event\EventDispatcher;
use Server\Components\Process\ProcessManager;
use Server\Components\SDHelp\SDHelpProcess;
use Server\CoreBase\Child;
use Server\CoreBase\CoreBase;
use Server\CoreBase\SwooleException;
use Server\Memory\Pool;

class TimerTask extends CoreBase
{
    protected $timer_tasks_used;
    /**
     * @var HttpClientPool
     */
    protected $consul;
    protected $leader_name;
    protected $id;
    const TIMERTASK = 'timer_task';

    public function __construct()
    {
        parent::__construct();
        $this->leader_name = $this->config['consul']['leader_service_name'];
        if (get_instance()->isConsul()) {
            $this->consul = new HttpClient(null, 'http://127.0.0.1:8500');
            swoole_timer_after(1000, function () {
                $this->updateFromConsul();
            });
        }
        $this->updateTimerTask();
        $this->timerTask();
        $this->id = swoole_timer_tick(1000, function () {
            $this->timerTask();
        });


    }


    /**
     * @param null $consulTask
     * @throws SwooleException
     */
    protected function updateTimerTask($consulTask = null)
    {
        $timer_tasks = $this->config->get('timerTask');
        if ($consulTask != null) {
            $timer_tasks = array_merge($timer_tasks, $consulTask);
        }
        $this->timer_tasks_used = [];
        foreach ($timer_tasks as $name => $timer_task) {
            $task_name = $timer_task['task_name'] ?? '';
            $model_name = $timer_task['model_name'] ?? '';
            if (empty($task_name) && empty($model_name)) {
                secho("[TIMERTASK]", "定时任务$name 配置错误，缺少task_name或者model_name.");
                continue;
            }
            $method_name = $timer_task['method_name'];
            $span = '';
            if (!array_key_exists('start_time', $timer_task)) {
                $start_time = time();
            } else {
                $start_time = strtotime(date($timer_task['start_time']));
                if (strpos($timer_task['start_time'], "i")) {
                    $span = " +1 minute";
                } else if (strpos($timer_task['start_time'], "H")) {
                    $span = " +1 hour";
                } else if (strpos($timer_task['start_time'], "d")) {
                    $span = " +1 day";
                } else if (strpos($timer_task['start_time'], "m")) {
                    $span = " +1 month";
                } else if (strpos($timer_task['start_time'], "Y")) {
                    $span = " +1 year";
                } else {
                    $span = '';
                }
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
            $max_exec = $timer_task['max_exec'] ?? -1;
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
                'delay' => $delay,
                'span' => $span
            ];
        }
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
                $timer_task['start_time'] = strtotime(date("Y-m-d H:i:s", $timer_task['start_time']) . $timer_task['span']);
                $timer_task['end_time'] = strtotime(date("Y-m-d H:i:s", $timer_task['end_time']) . $timer_task['span']);
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
                EventDispatcher::getInstance()->randomDispatch(TimerTask::TIMERTASK, $timer_task);
            }
        }
    }

    /**
     * @param int $index
     */
    public function updateFromConsul($index = 0)
    {
        $this->consul->setMethod('GET')
            ->setQuery(['index' => $index, 'key' => '*', 'recurse' => true])
            ->execute("/v1/kv/TimerTask/{$this->leader_name}/", function ($data) use ($index) {
                if ($data['statusCode'] < 0) {
                    $this->updateFromConsul($index);
                    return;
                }
                $body = json_decode($data['body'], true);
                $consulTask = [];
                if ($body != null) {
                    foreach ($body as $value) {
                        $consulTask[$value['Key']] = json_decode(base64_decode($value['Value']), true);
                    }
                    $this->updateTimerTask($consulTask);
                } else {
                    $this->updateTimerTask(null);
                }
                $index = $data['headers']['x-consul-index'];
                $this->updateFromConsul($index);
            });
    }

    /**
     * start
     */
    public static function start()
    {
        EventDispatcher::getInstance()->add(TimerTask::TIMERTASK, function (Event $event) {
            $timer_task = $event->data;
            $context = [];
            $child = Pool::getInstance()->get(Child::class);
            $child->setContext($context);
            if (!empty($timer_task['task_name'])) {
                $task = get_instance()->loader->task($timer_task['task_name'], $child);
                $startTime = getMillisecond();
                $path = "[TimerTask] " . $timer_task['task_name'] . "::" . $timer_task['method_name'];
                $task->startTask($timer_task['method_name'],[],-1, function () use (&$child, $startTime, $path) {
                    $child->destroy();
                    Pool::getInstance()->push($child);
                    ProcessManager::getInstance()->getRpcCall(SDHelpProcess::class, true)->addStatistics($path, getMillisecond() - $startTime);
                });
            } else {
                $model = get_instance()->loader->model($timer_task['model_name'], $child);
                $startTime = getMillisecond();
                $path = "[TimerTask] " . $timer_task['model_name'] . "::" . $timer_task['method_name'];
                sd_call_user_func([$model, $timer_task['method_name']]);
                $child->destroy();
                Pool::getInstance()->push($child);
                ProcessManager::getInstance()->getRpcCall(SDHelpProcess::class, true)->addStatistics($path, getMillisecond() - $startTime);
            }
        });
    }
}