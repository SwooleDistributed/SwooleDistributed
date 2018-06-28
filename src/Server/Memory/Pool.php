<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-2-27
 * Time: 下午12:49
 */

namespace Server\Memory;

use Server\CoreBase\SwooleException;

/**
 * 对象复用池
 * Class Pool
 * @package Server\Memory
 */
class Pool
{
    private static $instance;
    private $map;
    private $pool_count = [];

    public function __construct()
    {
        $this->map = [];
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new Pool();
        }
        return self::$instance;
    }

    /**
     * 获取一个
     * @param $class
     * @return mixed
     * @throws SwooleException
     */
    public function get($class)
    {
        $pool = $this->map[$class]??null;
        if ($pool == null) {
            $pool = $this->applyNewPool($class);
        }
        if (!$pool->isEmpty()) {
            return $pool->shift();
        } else {
            $this->addNewCount($class);
            return new $class;
        }
    }

    private function addNewCount($name)
    {
        if (isset($this->pool_count[$name])) {
            $this->pool_count[$name]++;
        } else {
            $this->pool_count[$name] = 1;
        }
    }

    private function applyNewPool($class)
    {
        if (array_key_exists($class, $this->map)) {
            throw new SwooleException('the name is exists in pool map');
        }
        $this->map[$class] = new \SplStack();
        return $this->map[$class];
    }

    /**
     * 返还一个
     * @param $classInstance
     * @throws SwooleException
     */
    public function push($classInstance)
    {
        $class = get_class($classInstance);
        $pool = $this->map[$class]??null;
        if ($pool == null) {
            $pool = $this->applyNewPool($class);
        }
        $pool->push($classInstance);
    }

    /**
     * 获取状态
     */
    public function getStatus()
    {
        $status = [];
        foreach ($this->map as $key => $value) {
            $status[$key . '[pool]'] = count($value);
            $status[$key . '[new]'] = $this->pool_count[$key] ?? 0;
        }
        return $status;
    }
}