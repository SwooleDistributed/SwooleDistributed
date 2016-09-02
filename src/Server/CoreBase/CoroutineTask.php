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
    protected $beforeFirstYield = true;
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
            if(!$routine){
                return;
            }
            $value = $routine->current();
            //嵌套的协程
            if ($value instanceof \Generator) {
                $this->stack->push($routine);
                $routine = $value;
                return;
            }
            $result = $value->getResult();
            if($result!=null){
                $routine->send($result);
            }
            //嵌套的协程返回
            if(!$routine->valid() && !$this->stack->isEmpty()) {
                $result = $routine->getReturn();
                $routine = $this->stack->pop();
                $routine->send($result);
            }
        } catch (\Exception $e) {

            if ($this->stack->isEmpty()) {
                /*
                    throw the exception
                */
                return;
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