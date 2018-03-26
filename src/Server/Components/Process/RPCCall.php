<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-15
 * Time: 上午10:37
 */

namespace Server\Components\Process;


use Server\Components\Event\Event;
use Server\Components\Event\EventDispatcher;
use Server\Memory\Pool;
use Server\Start;

class RPCCall
{
    const INIT_PROCESS = 0;
    const INIT_WORKERID = 1;
    /**
     * @var Process
     */
    protected $process;
    protected $oneWay;
    protected $workerId;
    protected $case;

    public function init($process, $oneWay = 'auto')
    {
        $this->case = self::INIT_PROCESS;
        $this->process = $process;
        $this->oneWay = $oneWay;
        return $this;
    }

    public function initworker($workerId, $oneWay = 'auto')
    {
        $this->case = self::INIT_WORKERID;
        $this->workerId = $workerId;
        $this->oneWay = $oneWay;
        return $this;
    }

    /**
     * @param $name
     * @param $arguments
     * @return bool|\Server\Components\Event\EventCoroutine
     */
    public function __call($name, $arguments)
    {
        if ($arguments == null) $arguments = [];
        $token = 0;
        switch ($this->case) {
            case self::INIT_PROCESS:
                if ($this->oneWay === 'auto') {
                    $this->oneWay = $this->process->isOneWay($name);
                }
                $token = $this->process->processRpcCall($name, $arguments, $this->oneWay, $this->process->worker_id);
                break;
            case self::INIT_WORKERID:
                if ($this->oneWay === 'auto') {
                    $this->oneWay = false;
                }
                $token = get_instance()->processRpcCall($name, $arguments, $this->oneWay, $this->workerId);
                break;
        }
        Pool::getInstance()->push($this);
        if (!$this->oneWay) {
            if ($token instanceof Event) {//说明是本进程的数据，直接返回数据就行
                $data = $token->data;
                Pool::getInstance()->push($token);
                return $data;
            } else {
                return EventDispatcher::getInstance()->addOnceCoroutine($token);
            }
        } else {
            return true;
        }
    }
}