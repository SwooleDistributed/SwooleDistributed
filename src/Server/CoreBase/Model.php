<?php
namespace Server\CoreBase;
/**
 * Model 涉及到数据有关的处理
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午12:00
 */
class Model extends CoreBase
{
    /**
     * @var \Server\DataBase\RedisAsynPool
     */
    public $redis_pool;
    /**
     * @var \Server\DataBase\MysqlAsynPool
     */
    public $mysql_pool;
    public function __construct()
    {
        parent::__construct();
        $this->redis_pool = get_instance()->redis_pool;
        $this->mysql_pool = get_instance()->mysql_pool;
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