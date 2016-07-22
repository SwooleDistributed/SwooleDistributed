<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午1:24
 */

namespace Server\CoreBase;


use Monolog\Logger;
use Noodlehaus\Config;
use Server\DataBase\RedisAsynPool;
use Server\Pack\IPack;

class CoreBase
{
    /**
     * 销毁标志
     * @var bool
     */
    public $is_destroy = false;
    /**
     * 子集
     * @var array
     */
    public $child_list = [];
    /**
     * 名称
     * @var string
     */
    public $core_name;
    /**
     * @var Loader
     */
    public $loader;
    /**
     * @var Logger
     */
    public $logger;
    /**
     * @var swoole_server
     */
    public $server;
    /**
     * @var Config
     */
    public $config;
    /**
     * @var IPack
     */
    public $pack;
    /**
     * @var RedisAsynPool
     */
    public $redis_pool;
    /**
     * @var \Server\DataBase\DbConnection
     */
    public $db;

    /**
     * Task constructor.
     */
    public function __construct()
    {
        $this->loader = get_instance()->loader;
        $this->logger = get_instance()->log;
        $this->server = get_instance()->server;
        $this->config = get_instance()->config;
        $this->pack = get_instance()->pack;
        $this->db = get_instance()->db;
        $this->redis_pool = get_instance()->redis_pool;
    }

    /**
     * 加入一个插件
     * @param $child CoreBase
     */
    public function addChild($child)
    {
        array_push($this->child_list, $child);
    }

    /**
     * 销毁，解除引用
     */
    public function destroy()
    {
        foreach ($this->child_list as $core_child) {
            $core_child->destroy();
        }
        $this->child_list = [];
        $this->is_destroy = true;
    }

    /**
     * 对象复用
     */
    public function reUse()
    {
        $this->is_destroy = false;
    }
}