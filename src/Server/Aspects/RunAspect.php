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
        if (!isset($this->context['RunStack'])) {
            $this->context['RunStack'] = [];
        }
        if (!isset($this->context['run_index_arr'])) {
            $this->context['run_index_arr'] = [];
        }
        $run_index = count($this->context['RunStack']);
        $this->context['run_index_arr'][] = $run_index;
        $this->context['RunStack'][$run_index] = " " . $this->class_name . "::" . $this->name;
        $this->run_start_time = microtime(true);
    }

    public function after()
    {
        $run_index = array_pop($this->context['run_index_arr']);
        if(empty($this->context['run_index_arr'])){
            unset($this->context['run_index_arr']);
        }
        $time = " -> " . ((microtime(true) - $this->run_start_time) * 1000) . " ms";
        $this->context['RunStack'][$run_index] = $this->context['RunStack'][$run_index] . $time;
        $count = count($this->context['RunStack']);
        for ($i = $run_index + 1; $i < $count; $i++) {
            $this->context['RunStack'][$i] = "─" . $this->context['RunStack'][$i];
        }
    }
}