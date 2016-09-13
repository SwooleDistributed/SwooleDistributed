<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\CoreBase;
class TaskCoroutine implements ICoroutineBase
{
    public $id;
    public $task_proxy_data;
    public $result = null;

    public function __construct($task_proxy_data, $id)
    {
        $this->task_proxy_data = $task_proxy_data;
        $this->id = $id;
        $this->send(function ($serv, $task_id, $data) {
            $this->result = $data;
        });
    }

    public function send($callback)
    {
        get_instance()->server->task($this->task_proxy_data, $this->id, $callback);
    }

    public function getResult()
    {
        return $this->result;
    }
}