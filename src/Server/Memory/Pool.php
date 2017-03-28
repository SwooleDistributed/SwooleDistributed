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
     */
    public function get($class)
    {
        $pool = $this->map[$class]??null;
        if ($pool == null) {
            $pool = $this->applyNewPool($class);
        }
        if ($pool->count()) {
            return $pool->shift();
        } else {
            return new $class;
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
}