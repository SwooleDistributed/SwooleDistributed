<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-15
 * Time: 上午10:37
 */

namespace Server\Components\Process;


use Server\Components\Event\EventDispatcher;
use Server\Memory\Pool;

class RPCCall
{
    /**
     * @var Process
     */
    protected $process;
    protected $oneWay;

    public function init($process, $oneWay = false)
    {
        $this->process = $process;
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
        $token = $this->process->call($name, $arguments, $this->oneWay);
        Pool::getInstance()->push($this);
        if (!$this->oneWay) {
            return EventDispatcher::getInstance()->addOnceCoroutine($token);
        } else {
            return true;
        }
    }
}