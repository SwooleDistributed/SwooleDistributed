<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午5:08
 */

namespace Server\CoreBase;


class CoroutineTask
{
    protected $stack;
    protected $routine;

    public function __construct(\Generator $routine)
    {
        $this->routine = $routine;
        $this->stack = new \SplStack();
    }

    /**
     * [run 协程调度]
     * @return [type]         [description]
     */
    public function run()
    {
        $routine = &$this->routine;
        try {
            if (!$routine) {
                return;
            }
            $value = $routine->current();
            //嵌套的协程
            if ($value instanceof \Generator) {
                $this->stack->push($routine);
                $routine = $value;
                return;
            }
            if ($value != null) {
                $result = $value->getResult();
                if ($result !== CoroutineNull::getInstance()) {
                    $routine->send($result);
                }
                //嵌套的协程返回
                while (!$routine->valid() && !$this->stack->isEmpty()) {
                    $result = $routine->getReturn();
                    $this->routine = $this->stack->pop();
                    $this->routine->send($result);
                }
            } else {
                $routine->next();
            }
        } catch (\Exception $e) {
            while (!$this->stack->isEmpty()) {
                $this->routine = $this->stack->pop();
                try {
                    $this->routine->throw($e);
                    break;
                } catch (\Exception $e) {

                }
            }
            if ($routine->controller != null) {
                call_user_func([$routine->controller, 'onExceptionHandle'], $e);
                $routine->controller = null;
            } else {
                $routine->throw($e);
            }
        }
    }

    /**
     * [isFinished 判断该task是否完成]
     * @return boolean [description]
     */
    public function isFinished()
    {
        return $this->stack->isEmpty() && !$this->routine->valid();
    }

    public function getRoutine()
    {
        return $this->routine;
    }
}