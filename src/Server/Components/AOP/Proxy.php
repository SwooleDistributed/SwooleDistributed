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
        $aspects = AOPManager::getInstance()->getAspects($this->class_name, $name);
        $count = count($aspects);
        for ($i = 0; $i < $count; $i++) {
            $instance = Pool::getInstance()->get($aspects[$i]['aspect_class']);
            $instance->init($aspects[$i],$this->own, $this->class_name, $name, $arguments);
            $aspects[$i]['instance'] = $instance;
            $before = $aspects[$i]['before_method'] ?? null;
            if (!empty($before)) {
                $aspects[$i]['instance']->$before();
            }
        }
        try {
            $result = sd_call_user_func_array([$this->own, $name], $arguments);
            return $result;
        } catch (\Throwable $e) {
            $isThrow = true;
            for ($i = $count-1; $i >= 0; $i--) {
                $throw = $aspects[$i]['throw_method'] ?? null;
                if (!empty($throw)) {
                    $aspects[$i]['instance']->$throw();
                }
            }
            throw  $e;
        } finally {
            for ($i = $count-1; $i >= 0; $i--) {
                $after = $aspects[$i]['after_method'] ?? null;
                if (!empty($after)) {
                    $aspects[$i]['instance']->$after($isThrow);
                }
                Pool::getInstance()->push($aspects[$i]['instance']);
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