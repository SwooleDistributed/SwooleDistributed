<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午5:08
 */

namespace Server\Coroutine;


use Server\CoreBase\SwooleException;
use Server\Memory\Pool;

class CoroutineTask
{
    protected $stack;
    protected $routine;
    /**
     * @var GeneratorContext
     */
    protected $generatorContext;
    protected $isError = false;


    public function __construct()
    {

    }

    /**
     * 对象池模式代替__construct
     * @param \Generator $routine
     * @param GeneratorContext $generatorContext
     * @return $this
     */
    public function init(\Generator $routine, GeneratorContext $generatorContext)
    {
        $this->stack = new \SplStack();
        $this->routine = $routine;
        $this->generatorContext = $generatorContext;
        return $this;
    }

    /**
     * 新方法，理论上会节约点
     * 协程调度
     */
    public function run()
    {
        while (true) {
            if ($this->isFinished()) {//已经出错了就直接return
                break;
            }
            $routine = &$this->routine;
            $flag = false;
            if (!$routine) {
                break;
            }
            $value = null;
            try {
                $value = $routine->current();
                $flag = true;
                //嵌套的协程
                if ($value instanceof \Generator) {
                    $this->generatorContext->addYieldStack($routine->key());
                    $this->stack->push($routine);
                    $routine = $value;
                    continue;
                }
                if ($value != null && $value instanceof ICoroutineBase) {
                    $result = $value->getResult();
                    if ($result !== CoroutineNull::getInstance()) {
                        $routine->send($result);
                        $value->destroy();
                    } else {//只有遇到异步的时候才中断循环
                        $value->setCoroutineTask($this);
                        break;
                    }
                    //嵌套的协程返回
                    while (!$routine->valid() && !$this->stack->isEmpty()) {
                        $result = $routine->getReturn();
                        $this->routine = $this->stack->pop();
                        $this->routine->send($result);
                        $this->generatorContext->popYieldStack();
                    }
                } else {
                    if ($routine->valid()) {
                        $routine->send($value);
                    } else {
                        //获得不到return说明可能是抛出了异常，这里可以停止了
                        try {
                            $result = $routine->getReturn();
                            if (count($this->stack) > 0) {
                                $this->routine = $this->stack->pop();
                                $this->routine->send($result);
                            }
                        } catch (\Exception $e) {
                            if (!$this->isError) {
                                $this->routine->throw($e);
                            }
                            $this->isError = true;
                        }
                    }
                }
            } catch (\Exception $e) {
                //这里$value如果是ICoroutineBase不需要进行销毁，否则有可能重复销毁
                if ($flag) {
                    $this->generatorContext->addYieldStack($routine->key());
                }
                $this->generatorContext->setErrorFile($e->getFile(), $e->getLine());
                $this->generatorContext->setErrorMessage($e->getMessage());
                $this->throwEx($routine, $e);
            }
        }
    }

    /**
     * 旧方法
     * 协程调度
     */
    public function run_old()
    {
        if ($this->isError) {//已经出错了就直接return
            return;
        }
        $routine = &$this->routine;
        $flag = false;
        if (!$routine) {
            return;
        }
        $value = null;
        try {
            $value = $routine->current();
            $flag = true;
            //嵌套的协程
            if ($value instanceof \Generator) {
                $this->generatorContext->addYieldStack($routine->key());
                $this->stack->push($routine);
                $routine = $value;
                return;
            }
            if ($value != null && $value instanceof ICoroutineBase) {
                $result = $value->getResult();
                if ($result !== CoroutineNull::getInstance()) {
                    $routine->send($result);
                    $value->destroy();
                } else {
                    $value->setCoroutineTask($this);
                    return;
                }
                //嵌套的协程返回
                while (!$routine->valid() && !$this->stack->isEmpty()) {
                    $result = $routine->getReturn();
                    $this->routine = $this->stack->pop();
                    $this->routine->send($result);
                    $this->generatorContext->popYieldStack();
                }
            } else {
                if ($routine->valid()) {
                    $routine->send($value);
                } else {
                    //获得不到return说明可能是抛出了异常，这里可以停止了
                    try {
                        $result = $routine->getReturn();
                        if (count($this->stack) > 0) {
                            $this->routine = $this->stack->pop();
                            $this->routine->send($result);
                        }
                    }catch (\Exception $e){
                        if(!$this->isError) {
                            $this->routine->throw($e);
                        }
                        $this->isError = true;
                    }
                }
            }
        } catch (\Exception $e) {
            //这里$value如果是ICoroutineBase不需要进行销毁，否则有可能重复销毁
            if ($flag) {
                $this->generatorContext->addYieldStack($routine->key());
            }
            $this->generatorContext->setErrorFile($e->getFile(), $e->getLine());
            $this->generatorContext->setErrorMessage($e->getMessage());
            $this->throwEx($routine,$e);
        }
    }

    protected function throwEx($routine,$e){
        try {
            $routine->throw($e);
            $this->routine = $routine;
        }catch (\Exception $e){
            if($this->stack->isEmpty()){
                $this->isError = true;
                if ($e instanceof SwooleException) {
                    $e->setShowOther($this->generatorContext->getTraceStack());
                }
                if ($this->generatorContext->getController() != null && method_exists($this->generatorContext->getController(), 'onExceptionHandle')) {
                    call_user_func([$this->generatorContext->getController(), 'onExceptionHandle'], $e);
                }
                return;
            }
            $routine = $this->stack->pop();
            $this->throwEx($routine,$e);
        }
    }
    /**
     * [isFinished 判断该task是否完成]
     * @return boolean [description]
     */
    public function isFinished()
    {
        try {
            $result = $this->isError || ($this->stack->isEmpty() && !$this->routine->valid());
        }catch (\Exception $e){
            $this->throwEx($this->routine,$e);
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
        $this->generatorContext->destroy();
        $this->generatorContext = null;
        $this->routine = null;
        $this->stack = null;
        $this->isError = false;
        Pool::getInstance()->push($this);
    }
}