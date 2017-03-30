<?php
namespace Server\CoreBase;

use Server\Memory\Pool;
use Server\SwooleMarco;

/**
 * Task的代理
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午12:11
 */
class TaskProxy extends CoreBase
{
    protected $task_id;
    protected $from_id;
    /**
     * task代理数据
     * @var mixed
     */
    private $task_proxy_data;

    /**
     * TaskProxy constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function initialization($task_id, $from_id, $worker_pid, $task_name, $method_name, $context)
    {
        $this->setContext($context);
    }

    /**
     * 代理
     * @param $name
     * @param $arguments
     * @return int
     */
    public function __call($name, $arguments)
    {
        $this->task_proxy_data =
            [
                'type' => SwooleMarco::SERVER_TYPE_TASK,
                'message' =>
                    [
                        'task_name' => $this->core_name,
                        'task_fuc_name' => $name,
                        'task_fuc_data' => $arguments,
                        'task_context' => $this->getContext(),
                    ]
            ];
        return $this->task_id;
    }

    /**
     * 开始异步任务
     * @param null $callback
     */
    public function startTask($callback = null)
    {
        get_instance()->server->task($this->task_proxy_data, -1, $callback);
    }

    /**
     * 异步的协程模式
     * @return TaskCoroutine
     */
    public function coroutineSend()
    {
        return Pool::getInstance()->get(TaskCoroutine::class)->init($this->task_proxy_data, -1);
    }

    /**
     * 开始同步任务
     */
    public function startTaskWait($timeOut = 0.5)
    {
        return get_instance()->server->taskwait($this->task_proxy_data, $timeOut, -1);
    }
}