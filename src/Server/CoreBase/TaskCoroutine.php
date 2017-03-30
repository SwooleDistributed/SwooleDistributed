<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\CoreBase;

use Server\Coroutine\CoroutineBase;
use Server\Memory\Pool;

class TaskCoroutine extends CoroutineBase
{
    public $id;
    public $task_proxy_data;
    public $task_id;

    public function __construct()
    {
        parent::__construct();
    }

    public function init($task_proxy_data, $id)
    {
        $this->task_proxy_data = $task_proxy_data;
        $this->id = $id;
        $this->send(function ($serv, $task_id, $data) {
            $this->result = $data;
        });
        return $this;
    }

    public function send($callback)
    {
        $this->task_id = get_instance()->server->worker_id . get_instance()->server->task($this->task_proxy_data, $this->id, $callback);
    }

    public function destroy()
    {
        parent::destroy();
        $this->task_id = null;
        Pool::getInstance()->push($this);
    }

    protected function onTimerOutHandle()
    {
        parent::onTimerOutHandle();
        get_instance()->stopTask($this->task_id);
    }
}
