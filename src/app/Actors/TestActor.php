<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-1-2
 * Time: 下午3:24
 */

namespace app\Actors;


use Server\CoreBase\Actor;
use Server\CoreBase\ChildProxy;

class TestActor extends Actor
{
    public function __construct($proxy = ChildProxy::class)
    {
        parent::__construct($proxy);
    }

    public function initialization($name, $saveContext = null)
    {
        parent::initialization($name, $saveContext);
        $this->setStatus("status", 1);
    }

    public function test1()
    {
        $this->redis_pool->getCoroutine()->set("test", 199);
        return 1;
    }

    public function test2()
    {
        $result = $this->redis_pool->getCoroutine()->get("test");
        return $result;
    }

    public function test3()
    {
        $result = $this->redis_pool->getCoroutine()->del("test");
        return "3";
    }

    /**
     * 处理注册状态
     * @param $key
     * @param $value
     */
    public function registStatusHandle($key, $value)
    {
        switch ($key) {
             case 'status':
                 switch ($value) {
                     case 1:
                         break;
                 }
                 break;
        }
    }
}