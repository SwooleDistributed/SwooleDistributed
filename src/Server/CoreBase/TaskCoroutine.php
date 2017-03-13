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
        get_instance()->server->task($this->task_proxy_data, $this->id, $callback);
    }

    public function destroy()
    {
        parent::destroy();
        Pool::getInstance()->push(TaskCoroutine::class, $this);
    }
}
