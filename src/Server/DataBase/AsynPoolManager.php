<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-25
 * Time: 上午10:25
 */

namespace Server\DataBase;


use Server\SwooleServer;

class AsynPoolManager
{
    /**
     * @var SwooleServer
     */
    protected $swoole_server;
    protected $process;
    protected $registDir = [];
    public function __construct($process, $swoole_server)
    {
        $this->process = $process;
        $this->swoole_server = $swoole_server;
    }

    /**
     * event_add
     */
    public function event_add(){
        swoole_event_add($this->process->pipe, [$this, 'getPipeMessage']);
    }
    /**
     * 管道来消息
     * @param $pipe
     */
    public function getPipeMessage($pipe)
    {
        $read = $this->process->read();
        $baodata = unserialize($read);
        $asyn = $this->registDir[$baodata['asyn_name']];
        call_user_func([$asyn, 'execute'], $baodata);
    }

    /**
     * 分发消息
     * @param $data
     */
    public function distribute($data)
    {
        $asyn = $this->registDir[$data['asyn_name']];
        call_user_func([$asyn, 'distribute'], $data);
    }

    /**
     * 注册
     * @param $asyn
     */
    public function registAsyn(IAsynPool $asyn)
    {
        $this->registDir[$asyn->getAsynName()] = $asyn;
        $asyn->server_init($this->swoole_server, $this);
    }

    /**
     * 写入管道
     * @param $asyn_name
     * @param $data
     * @param $worker_id
     */
    public function writePipe(IAsynPool $asyn, $data, $worker_id)
    {
        $data['asyn_name'] = $asyn->getAsynName();
        $data['worker_id'] = $worker_id;
        //写入管道
        $this->process->write(serialize($data));
    }

    /**
     * 分发消息给worker
     * @param $data
     */
    public function sendMessageToWorker(IAsynPool $asyn, $data)
    {
        $workerID = $data['worker_id'];
        $message = $this->swoole_server->packSerevrMessageBody($asyn->getMessageType(), $data);
        $this->swoole_server->server->sendMessage($message, $workerID);
    }
}