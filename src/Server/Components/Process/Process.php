<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-15
 * Time: 上午9:41
 */

namespace Server\Components\Process;


use Server\Components\Event\EventDispatcher;
use Server\Coroutine\Coroutine;
use Server\SwooleMarco;

abstract class Process
{
    protected $process;
    public $worker_id;
    protected $config;
    protected $log;
    protected $token = 0;
    /**
     * 协程支持
     * @var bool
     */
    protected $coroutine_need = true;

    /**
     * Process constructor.
     * @param $name
     * @param $worker_id
     * @param bool $coroutine_need
     */
    public function __construct($name, $worker_id, $coroutine_need = true)
    {
        $this->name = $name;
        $this->worker_id = $worker_id;
        $this->coroutine_need = $coroutine_need;
        $this->process = new \swoole_process([$this, '__start'], false, 2);
        $this->config = get_instance()->config;
        $this->log = get_instance()->log;
        get_instance()->server->addProcess($this->process);
    }

    public function __start($process)
    {
        get_instance()->workerId = $this->worker_id;
        if (!isDarwin()) {
            $process->name($this->name);
        }
        swoole_event_add($process->pipe, [$this, 'onRead']);
        get_instance()->server->worker_id = $this->worker_id;
        get_instance()->server->taskworker = false;
        if ($this->coroutine_need) {
            //协成支持
            Coroutine::init();
            Coroutine::startCoroutine([$this, 'start'], [$process]);
        } else {
            $this->start($process);
        }
    }


    /**
     * @param $process
     */
    public abstract function start($process);

    /**
     * onRead
     */
    public function onRead()
    {
        $recv = \swoole_serialize::unpack($this->process->read(64 * 1024));
        $message = $recv['message'];
        switch ($recv['type']) {
            case SwooleMarco::PROCESS_RPC:
                $this->run($message);
                break;
            case SwooleMarco::PROCESS_RPC_RESULT:
                EventDispatcher::getInstance()->dispatch($message['token'], $message['result'], true);
                break;
        }
    }

    protected function run($message)
    {
        $func = $message['func'];
        $result = call_user_func_array([$this, $func], $message['arg']);
        if ($result instanceof \Generator)//需要协程调度
        {
            if (!$this->coroutine_need) {
                throw new \Exception("该进程不支持协程调度器");
            }
            Coroutine::startCoroutine(function () use ($result, $message) {
                $result = yield $result;
                if (!$message['oneWay']) {
                    $newMessage['result'] = $result;
                    $newMessage['token'] = $message['token'];
                    $data = get_instance()->packServerMessageBody(SwooleMarco::PROCESS_RPC_RESULT, $newMessage);
                    $this->sendMessage($data, $message['worker_id']);
                }
            });
        } else {
            if (!$message['oneWay']) {
                $newMessage['result'] = $result;
                $newMessage['token'] = $message['token'];
                $data = get_instance()->packServerMessageBody(SwooleMarco::PROCESS_RPC_RESULT, $newMessage);
                $this->sendMessage($data, $message['worker_id']);
            }
        }
    }

    /**
     * @param $data
     * @param $worker_id
     */
    protected function sendMessage($data, $worker_id)
    {
        if ($this->isUserProcess($worker_id)) {
            $process = ProcessManager::getInstance()->getProcessFromID($worker_id);
            $process->process->write(\swoole_serialize::pack($data));
        } else {
            get_instance()->server->sendMessage($data, $worker_id);
        }
    }

    /**
     * @param $name
     * @param $arguments
     * @param $oneWay
     * @return string
     */
    public function call($name, $arguments, $oneWay)
    {
        $this->token++;
        $worker_id = get_instance()->getWorkerId();
        $message['worker_id'] = $worker_id;
        $message['arg'] = $arguments;
        $message['func'] = $name;
        $message['token'] = 'ProcessRpc:' . $this->token;
        $message['oneWay'] = $oneWay;
        if ($worker_id == $this->worker_id) {
            $this->run($message);
            return $message['token'];
        }
        $this->process->write(
            \swoole_serialize::pack(
                get_instance()->packServerMessageBody(SwooleMarco::PROCESS_RPC, $message)
            )
        );
        return $message['token'];
    }

    /**
     * 是不是用户进程
     * @param $worker_id
     * @return bool
     */
    protected function isUserProcess($worker_id)
    {
        return $worker_id >= get_instance()->worker_num + get_instance()->task_num ? true : false;
    }
}