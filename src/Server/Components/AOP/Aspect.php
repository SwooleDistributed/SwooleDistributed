<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/1
 * Time: 10:23
 */

namespace Server\Components\AOP;


use Server\Asyn\Mysql\Miner;
use Server\CoreBase\Child;
use Server\CoreBase\CoreBase;

class Aspect extends CoreBase
{
    /**
     * @var Child
     */
    protected $own;
    protected $name;
    protected $args;
    protected $class_name;
    /**
     * @var Miner
     */
    protected $db;
    /**
     * @var \Redis
     */
    protected $redis;
    public function init($own,$class_name,$name,$args)
    {
        $this->own = $own;
        $this->name = $name;
        $this->args = $args;
        $this->class_name = $class_name;
        $this->db = $this->loader->mysql("mysqlPool",$own);
        $this->redis = $this->loader->redis("redisPool",$own);
        $this->setContext($own->getContext());
    }
}