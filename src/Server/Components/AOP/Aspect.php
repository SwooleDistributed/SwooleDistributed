<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/1
 * Time: 10:23
 */

namespace Server\Components\AOP;


use Server\CoreBase\Child;

class Aspect
{
    /**
     * @var Child
     */
    protected $own;
    protected $name;
    protected $args;
    protected $class_name;
    public function init($own,$class_name,$name,$args)
    {
        $this->own = $own;
        $this->name = $name;
        $this->args = $args;
        $this->class_name = $class_name;
    }
}