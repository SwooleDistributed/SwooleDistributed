<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-15
 * Time: 上午9:41
 */

namespace Server\Components\Process;


use Server\Coroutine\Coroutine;
use Server\SwooleMarco;

class Process
{
    protected $process;
    protected $worker_id;
    protected $config;
    protected $token = 0;
    /**
     * 协成支持
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
        $this->process = new \swoole_process([$this, 'start'], false, 2);
        $this->config = get_instance()->config;
        get_instance()->server->addProcess($this->process);
    }

    /**
     * @param $process
     */
    public function start($process)
    {
        if (!isDarwin()) {
            $process->name($this->name);
        }
        if ($this->coroutine_need) {
            //协成支持
            Coroutine::init();
        }
        swoole_event_add($process->pipe, [$this, 'onRead']);
        get_instance()->server->worker_id = $this->worker_id;

    }

    /**
     * onRead
     */
    public function onRead()
    {
        $recv = \swoole_serialize::unpack($this->process->read(64 * 1024));
        $message = $recv['message'];
        $func = $message['func'];
        $result = call_user_func_array([$this, $func], $message['arg']);
        if (!$message['oneWay']) {
            $newMessage['result'] = $result;
            $newMessage['token'] = $message['token'];
            $data = get_instance()->packSerevrMessageBody(SwooleMarco::PROCESS_RPC, $newMessage);
            get_instance()->server->sendMessage($data, $message['worker_id']);
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
        $worker_id = get_instance()->server->worker_id;
        $message['worker_id'] = $worker_id;
        $message['arg'] = $arguments;
        $message['func'] = $name;
        $message['token'] = 'ProcessRpc:' . $this->token;
        $message['oneWay'] = $oneWay;
        $this->process->write(
            \swoole_serialize::pack(
                get_instance()->packSerevrMessageBody(SwooleMarco::PROCESS_RPC, $message)
            )
        );
        return $message['token'];
    }

}