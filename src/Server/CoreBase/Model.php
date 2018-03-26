<?php
namespace Server\CoreBase;
use Server\Asyn\Mysql\Miner;

/**
 * Model 涉及到数据有关的处理
 * 对象池模式，实例会被反复使用，成员变量缓存数据记得在销毁时清理
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午12:00
 */
class Model extends CoreBase
{
    /**
     * @var Miner
     */
    protected $db;

    /**
     * @var \Redis
     */
    protected $redis;
    public function __construct($proxy = ChildProxy::class)
    {
        parent::__construct($proxy);
    }

    /**
     * 当被loader时会调用这个方法进行初始化
     */
    public function initialization(&$context)
    {
        $this->setContext($context);
        if ($this->mysql_pool != null) {
            $this->installMysqlPool($this->mysql_pool);
            $this->db = $this->mysql_pool->dbQueryBuilder;
        }
        if ($this->redis_pool != null) {
            $this->redis = $this->redis_pool->getCoroutine();
        }
    }

    /**
     * 销毁回归对象池
     */
    public function destroy()
    {
        parent::destroy();
        ModelFactory::getInstance()->revertModel($this);
    }

}