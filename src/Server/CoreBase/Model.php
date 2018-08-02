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
    public $db;

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
     * @param $context
     */
    public function initialization(&$context)
    {
        $this->setContext($context);
        $this->redis = $this->loader->redis("redisPool",$this);
        $this->db = $this->loader->mysql("mysqlPool",$this);
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