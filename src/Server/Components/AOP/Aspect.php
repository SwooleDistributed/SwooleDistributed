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
    protected $aspect_config;
    protected $class_name;
    /**
     * @var Miner
     */
    protected $db;
    /**
     * @var \Redis
     */
    protected $redis;
    public function init($aspect_config,$own,$class_name,$name,$args)
    {
        $this->own = $own;
        $this->name = $name;
        $this->args = $args;
        $this->aspect_config = $aspect_config;
        $this->class_name = $class_name;
        $this->db = $this->loader->mysql("mysqlPool",$own);
        $this->redis = $this->loader->redis("redisPool",$own);
        $this->setContext($own->getContext());
    }

    /**
     * @param $key
     * @return null
     */
    protected function getAspectConfig($key)
    {
        return $this->aspect_config[$key]??null;
    }
}