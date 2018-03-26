<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午1:24
 */

namespace Server\CoreBase;


use Monolog\Logger;
use Noodlehaus\Config;
use Server\Asyn\Mysql\MysqlAsynPool;
use Server\Asyn\Redis\RedisRoute;
use Server\Memory\Pool;

class CoreBase extends Child
{
    /**
     * 销毁标志
     * @var bool
     */
    public $is_destroy = false;

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
     * @var RedisRoute
     */
    public $redis_pool;
    /**
     * @var MysqlAsynPool
     */
    public $mysql_pool;

    protected $dbQueryBuilders = [];

    /**
     * Task constructor.
     * @param string $proxy
     */
    public function __construct($proxy = ChildProxy::class)
    {
        parent::__construct($proxy);
        if (!empty(get_instance())) {
            $this->loader = get_instance()->loader;
            $this->logger = get_instance()->log;
            $this->server = get_instance()->server;
            $this->config = get_instance()->config;
            $this->redis_pool = RedisRoute::getInstance();
            $this->mysql_pool = get_instance()->getAsynPool('mysqlPool');
        }
    }

    /**
     * 安装MysqlPool
     * @param MysqlAsynPool $mysqlPool
     */
    protected function installMysqlPool(MysqlAsynPool $mysqlPool)
    {
        $this->dbQueryBuilders[$mysqlPool->getActveName()] = $mysqlPool->installDbBuilder();
    }

    /**
     * 销毁，解除引用
     */
    public function destroy()
    {
        parent::destroy();
        $this->is_destroy = true;
        foreach ($this->dbQueryBuilders as $dbQueryBuilder) {
            $dbQueryBuilder->clear();
            $dbQueryBuilder->setClient(null);
            Pool::getInstance()->push($dbQueryBuilder);
        }
        $this->dbQueryBuilders = [];
    }

    /**
     * 对象复用
     */
    public function reUse()
    {
        $this->is_destroy = false;
    }

    /**
     * 打印日志
     * @param $message
     * @param int $level
     */
    protected function log($message, $level = Logger::DEBUG)
    {
        try {
            $this->logger->addRecord($level, $message, $this->getContext());
        } catch (\Exception $e) {

        }
    }
}