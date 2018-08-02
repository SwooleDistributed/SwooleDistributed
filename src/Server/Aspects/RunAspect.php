<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/1
 * Time: 12:07
 */

namespace Server\Aspects;


use Server\Components\AOP\Aspect;

class RunAspect extends Aspect
{
    protected $run_start_time;

    public function before()
    {
        if (!isset($this->own->getContext()['RunStack'])) {
            $this->own->getContext()['RunStack'] = [];
        }
        if (!isset($this->own->getContext()['run_index_arr'])) {
            $this->own->getContext()['run_index_arr'] = [];
        }
        $run_index = count($this->own->getContext()['RunStack']);
        $this->own->getContext()['run_index_arr'][] = $run_index;
        $this->own->getContext()['RunStack'][$run_index] = " " . $this->class_name . "::" . $this->name;
        $this->run_start_time = microtime(true);
    }

    public function after()
    {
        $run_index = array_pop($this->own->getContext()['run_index_arr']);
        $time = " -> " . ((microtime(true) - $this->run_start_time) * 1000) . " ms";
        $this->own->getContext()['RunStack'][$run_index] = $this->own->getContext()['RunStack'][$run_index] . $time;
        $count = count($this->own->getContext()['RunStack']);
        for ($i = $run_index + 1; $i < $count; $i++) {
            $this->own->getContext()['RunStack'][$i] = "─" . $this->own->getContext()['RunStack'][$i];
        }
    }
}