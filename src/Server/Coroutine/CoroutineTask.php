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
    protected $isError;


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
     * 协程调度
     */
    public function run()
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
                    $result = $routine->getReturn();
                    if (count($this->stack) > 0) {
                        $this->routine = $this->stack->pop();
                        $this->routine->send($result);
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
            while (!$this->stack->isEmpty()) {
                $this->routine = $this->stack->pop();
                try {
                    $this->routine->throw($e);
                    break;
                } catch (\Exception $e) {
                    $this->isError = true;
                    if ($e instanceof SwooleException) {
                        $e->setShowOther($this->generatorContext->getTraceStack());
                    }
                    if ($this->generatorContext->getController() != null && method_exists($this->generatorContext->getController(), 'onExceptionHandle')) {
                        call_user_func([$this->generatorContext->getController(), 'onExceptionHandle'], $e);
                    } else {
                        $routine->throw($e);
                    }
                }
            }
        }
    }

    /**
     * [isFinished 判断该task是否完成]
     * @return boolean [description]
     */
    public function isFinished()
    {
        return $this->isError || ($this->stack->isEmpty() && !$this->routine->valid());
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
        Pool::getInstance()->push(CoroutineTask::class, $this);
    }
}