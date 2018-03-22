<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-10-12
 * Time: 下午7:17
 */

namespace Server\CoreBase;


use Server\Components\AOP\Proxy;

class ChildProxy extends Proxy
{
    protected $class_name;
    protected $run_index_arr = [];
    protected $run_start_time;
    public function __construct($own)
    {
        parent::__construct($own);
        $this->class_name = get_class($own);
    }

    /**
     * 设置上下文
     * @param $context
     */
    public function setContext(&$context)
    {
        $this->own->setContext($context);
    }

    public function beforeCall($name, $arguments = null)
    {
        if(!isset($this->own->getContext()['RunStack'])){
            $this->own->getContext()['RunStack'] = [];
        }
        $run_index = count($this->own->getContext()['RunStack']);
        $this->run_index_arr[] = $run_index;
        $this->own->getContext()['RunStack'][$run_index] = " ".$this->class_name . "::" . $name;
        $this->run_start_time = microtime(true);
    }

    public function afterCall($name, $arguments = null)
    {
        $run_index = array_pop($this->run_index_arr);
        $time = " -> " . ((microtime(true) - $this->run_start_time)*1000)." ms";
        $this->own->getContext()['RunStack'][$run_index] = $this->own->getContext()['RunStack'][$run_index] . $time;
        $count = count($this->own->getContext()['RunStack']);
        for ($i = $run_index + 1; $i < $count; $i++) {
            $this->own->getContext()['RunStack'][$i] = "─" . $this->own->getContext()['RunStack'][$i];
        }
    }
}