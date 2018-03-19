<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-15
 * Time: 上午9:41
 */

namespace Server\Components\Process;


use Server\Components\Event\EventDispatcher;
use Server\SwooleMarco;

abstract class Process extends ProcessRPC
{
    public $process;
    public $worker_id;
    protected $config;
    protected $log;
    protected $token = 0;
    protected $params;

    /**
     * Process constructor.
     * @param string $name
     * @param $worker_id
     * @param $params
     */
    public function __construct($name, $worker_id, $params)
    {
        parent::__construct();
        $this->name = $name;
        $this->worker_id = $worker_id;
        get_instance()->workerId = $worker_id;
        $this->config = get_instance()->config;
        $this->log = get_instance()->log;
        $this->params = $params;
        if (get_instance()->server != null) {
            $this->process = new \swoole_process([$this, '__start'], false, 2);
            get_instance()->server->addProcess($this->process);
        }
    }

    public function __start($process)
    {
        \swoole_process::signal(SIGTERM, [$this, "__shutDown"]);
        get_instance()->workerId = $this->worker_id;
        if (!isDarwin()) {
            $process->name($this->name);
        }
        swoole_event_add($process->pipe, [$this, 'onRead']);
        get_instance()->server->worker_id = $this->worker_id;
        get_instance()->server->taskworker = false;
        go(function () use ($process) {
            $this->start($process);
        });
    }


    /**
     * @param $process
     */
    public abstract function start($process);

    /**
     * 关服处理
     */
    public function __shutDown()
    {
        $this->onShutDown();
        secho("Process:$this->worker_id", get_class($this) . "关闭成功");
        exit();
    }

    abstract protected function onShutDown();

    /**
     * onRead
     */
    public function onRead()
    {
        $recv = \swoole_serialize::unpack($this->process->read(64 * 1024));
        $this->readData($recv);
    }

    /**
     * @param $data
     */
    public function readData($data)
    {
        go(function () use ($data) {
            $message = $data['message'];
            switch ($data['type']) {
                case SwooleMarco::PROCESS_RPC:
                    $this->processPpcRun($message);
                    break;
                case SwooleMarco::PROCESS_RPC_RESULT:
                    EventDispatcher::getInstance()->dispatch($message['token'], $message['result'], true);
                    break;
            }
        });
    }

    /**
     * 执行外部命令
     * @param $path
     * @param $params
     */
    protected function exec($path, $params)
    {
        $this->process->exec($path, $params);
    }
}