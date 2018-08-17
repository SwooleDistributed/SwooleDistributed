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
     * @param string $proxy
     */
    public function __construct($proxy = ChildProxy::class)
    {
        parent::__construct($proxy);
    }

    public function initialization($task_id, $from_id, $worker_pid, $task_name, $method_name, $context)
    {
        $this->setContext($context);
    }

    private function help_call($name, $arguments)
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
    }

    /**
     * 代理
     * @param $name
     * @param $arguments
     * @return int
     */
    public function __call($name, $arguments)
    {
        $this->help_call($name, $arguments);
        return Pool::getInstance()->get(TaskCoroutine::class)->init($this->task_proxy_data, -1, null);
    }


    /**
     * 开始异步任务
     * @param $name
     * @param $arguments
     * @param int $dst_worker_id
     * @param null $callback
     */
    public function startTask($name, $arguments, $dst_worker_id = -1, $callback = null)
    {
        $this->help_call($name, $arguments);
        get_instance()->server->task($this->task_proxy_data, $dst_worker_id, $callback);
    }

    /**
     * 异步的协程模式
     * @param $name
     * @param $arguments
     * @param int $dst_worker_id
     * @param callable|null $set
     * @return TaskCoroutine
     */
    public function call($name, $arguments, $dst_worker_id = -1, callable $set = null)
    {
        $this->help_call($name, $arguments);
        return Pool::getInstance()->get(TaskCoroutine::class)->init($this->task_proxy_data, $dst_worker_id, $set);
    }
}