<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\CoreBase;

use Server\Coroutine\CoroutineBase;
use Server\Coroutine\CoroutineNull;
use Server\Memory\Pool;
use Server\Start;

class TaskCoroutine extends CoroutineBase
{
    public $id;
    public $task_proxy_data;
    public $task_id;

    public function init($task_proxy_data, $id, $set)
    {
        $this->task_proxy_data = $task_proxy_data;
        $this->id = $id;
        $this->set($set);
        $this->send(function ($serv, $task_id, $data) {
            if ($data instanceof CoroutineNull) {
                $data = null;
            }
            $this->coPush($data);
        });
        $d = "[".$task_proxy_data['message']['task_name'] ."::". $task_proxy_data['message']['task_fuc_name']."]";
        $this->request = "[Task]$d";
        if (Start::getDebug()){
            secho("TASK",$d);
        }
        return $this->returnInit();
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
