<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-10-12
 * Time: 下午7:17
 */

namespace Server\Components\AOP;


use Server\Memory\Pool;

abstract class Proxy
{
    /**
     * @var mixed
     */
    protected $own;
    protected $class_name;
    public function __construct($own)
    {
        $this->own = $own;
        $this->class_name = get_class($own);
    }
    /**
     * @param $name
     * @param $arguments
     * @return mixed
     * @throws \Server\CoreBase\SwooleException
     * @throws \Throwable
     */
    public function __call($name, $arguments)
    {
        $isThrow = false;
        $aspects = AOPManager::getInstance()->getAspects($this->class_name,$name);
        foreach ($aspects as &$aspect){
            $aspect['instance'] = Pool::getInstance()->get($aspect['aspect_class']);
            $aspect['instance']->init($this->own, $this->class_name,$name, $arguments);
            $instance = $aspect['instance'];
            $before = $aspect['before_method'];
            if(!empty($before)) {
                $instance->$before();
            }
        }
        try {
            $result = sd_call_user_func_array([$this->own, $name], $arguments);
            return $result;
        }catch (\Throwable $e){
            $isThrow = true;
            foreach ($aspects as $aspect){
                $instance = $aspect['instance'];
                $throw = $aspect['throw_method'];
                if(!empty($throw)) {
                    $instance->$throw($e);
                }
            }
            throw  $e;
        }finally{
            foreach ($aspects as $aspect){
                $instance = $aspect['instance'];
                $after = $aspect['after_method'];
                if(!empty($after)) {
                    $instance->$after($isThrow);
                }
                Pool::getInstance()->push($instance);
            }
        }
    }

    public function __set($name, $value)
    {
        $this->own->$name = $value;
    }

    public function __get($name)
    {
        return $this->own->$name;
    }

    public function getOwn()
    {
        return $this->own;
    }
}