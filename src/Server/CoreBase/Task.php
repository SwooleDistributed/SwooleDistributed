<?php
namespace Server\CoreBase;
use Monolog\Logger;

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

    public function initialization($task_id, $worker_pid, $task_name, $method_name, $context)
    {
        $this->task_id = $task_id;
        get_instance()->tid_pid_table->set($this->task_id, ['pid' => $worker_pid, 'des' => "$task_name::$method_name", 'start_time' => time()]);
        $this->setContext($context);
        $this->start_run_time = microtime(true);
        $this->context['task_name'] = "$task_name:$method_name";
    }

    public function destroy()
    {
        $this->context['execution_time'] = (microtime(true) - $this->start_run_time)*1000;
        $this->log('Efficiency monitor',Logger::INFO);
        get_instance()->tid_pid_table->del($this->task_id);
        parent::destroy();
        $this->task_id = 0;
    }

    /**
     * 检查中断信号返回本Task是否该中断
     * @return bool
     */
    protected function checkInterrupted()
    {
        $interrupted = pcntl_signal_dispatch();
        if ($interrupted == false) {
            return false;
        }
        //表总0获得值代表的是需要中断的id
        $interrupted_task_id = get_instance()->tid_pid_table->get(0)['pid'];
        //读取后可以释放锁了
        get_instance()->task_lock->unlock();
        if ($interrupted_task_id == $this->task_id) {
            return true;
        }
        return false;
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