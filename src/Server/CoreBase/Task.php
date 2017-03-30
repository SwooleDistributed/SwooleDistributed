<?php
namespace Server\CoreBase;
use Monolog\Logger;
use Server\Memory\Pool;

/**
 * Task 异步任务
 * 在worker中的Task会被构建成TaskProxy。这个实例是单例的，
 * 所以发起task请求时每次都要使用loader给TaskProxy赋值，不能缓存重复使用，以免数据错乱。
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午12:00
 */
class Task extends TaskProxy
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $task_id
     * @param $from_id 来自哪个worker进程
     * @param $worker_pid 在哪个task进程中运行
     * @param $task_name
     * @param $method_name
     * @param $context
     */
    public function initialization($task_id, $from_id, $worker_pid, $task_name, $method_name, $context)
    {
        $this->task_id = $task_id;
        $this->from_id = $from_id;
        get_instance()->tid_pid_table->set($this->from_id.$this->task_id, ['pid' => $worker_pid, 'des' => "$task_name::$method_name", 'start_time' => time()]);
        $this->setContext($context);
        $this->start_run_time = microtime(true);
        $this->context['task_name'] = "$task_name:$method_name";
    }

    public function destroy()
    {
        if($this->isEfficiencyMonitorEnable) {
            $this->context['execution_time'] = (microtime(true) - $this->start_run_time) * 1000;
            $this->log('Efficiency monitor', Logger::INFO);
        }
        get_instance()->tid_pid_table->del($this->from_id.$this->task_id);
        parent::destroy();
        $this->task_id = 0;
        Pool::getInstance()->push($this);
    }

    /**
     * sendToUid
     * @param $uid
     * @param $data
     */
    protected function sendToUid($uid, $data)
    {
        $data = $this->pack->pack($data);
        get_instance()->sendToUid($uid, $data);
    }

    /**
     * sendToUids
     * @param $uids
     * @param $data
     */
    protected function sendToUids($uids, $data)
    {
        $data = $this->pack->pack($data);
        get_instance()->sendToUids($uids, $data);
    }

    /**
     * sendToAll
     * @param $data
     */
    protected function sendToAll($data)
    {
        $data = $this->pack->pack($data);
        get_instance()->sendToAll($data);
    }
}