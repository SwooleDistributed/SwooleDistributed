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

    public function init($process)
    {
        $this->process = $process;
        return $this;
    }

    /**
     * @param $name
     * @param $arguments
     * @return \Server\Components\Event\EventCoroutine
     */
    public function __call($name, $arguments)
    {
        $token = $this->process->__call($name, $arguments);
        Pool::getInstance()->push($this);
        return EventDispatcher::getInstance()->addOnceCoroutine($token);
    }
}