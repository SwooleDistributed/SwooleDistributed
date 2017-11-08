<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午5:08
 */

namespace Server\Coroutine;


use Server\Memory\Pool;

class CoroutineTask
{
    /**
     * @var \SplStack
     */
    protected $stack;
    /**
     * @var \Generator
     */
    protected $routine;

    /**
     * @var \Exception
     */
    protected $e;


    public function __construct()
    {
        $this->stack = new \SplStack();
    }

    /**
     * 对象池模式代替__construct
     * @param \Generator $routine
     * @return $this
     */
    public function init(\Generator $routine)
    {
        while (!$this->stack->isEmpty()) {
            $this->stack->pop();
        }
        $this->routine = $routine;
        return $this;
    }

    /**
     * 协程调度
     */
    public function run()
    {
        if (!$this->routine) {
            return;
        }
        try {
            $value = $this->routine->current();
            //嵌套的协程
            if ($value instanceof \Generator) {
                $this->stack->push($this->routine);
                $this->routine = $value;
                $this->run();
                return;
            }
            //ICoroutineBase
            if ($value != null && $value instanceof ICoroutineBase) {
                $result = $value->getResult();
                if ($result !== CoroutineNull::getInstance()) {
                    $this->routine->send($result);
                    $value->destroy();
                    if (!$this->stack->isEmpty()) {
                        $this->run();
                    }
                } else {
                    $value->setCoroutineTask($this);
                }
                return;
            }
            //有效的
            if ($this->routine->valid()) {
                $this->routine->send($value);
                $this->run();
                return;
            }
            //返回上级
            try {
                $result = $this->routine->getReturn();
            } catch (\Exception $e) {
                $result = '';
            }
            if (!$this->stack->isEmpty()) {
                $this->routine = $this->stack->pop();
                $this->routine->send($result);
                $this->run();
                return;
            }
        } catch (\Exception $e) {
            $this->throwEx($this->routine, $e);
        }
    }

    /**
     * @param $routine
     * @param $e
     */
    protected function throwEx(\Generator $routine, \Exception $e)
    {
        try {
            $this->e = $e;
            $routine->throw($e);
            $this->routine = $routine;
            $this->run();
        } catch (\Exception $e) {
            if (!$this->stack->isEmpty()) {
                $routine = $this->stack->pop();
                $this->throwEx($routine, $e);
            }
        }
    }

    /**
     * [isFinished 判断该task是否完成]
     * @return boolean [description]
     */
    public function isFinished()
    {
        try {
            $result = $this->stack->isEmpty() && !$this->routine->valid();
        } catch (\Exception $e) {
            $this->throwEx($this->routine, $e);
            $result = true;
        }
        return $result;
    }

    public function getRoutine()
    {
        return $this->routine;
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        $this->e = null;
        $this->routine = null;
        Pool::getInstance()->push($this);
    }

    /**
     * @return \Exception
     */
    public function getEx()
    {
        return $this->e;
    }
}