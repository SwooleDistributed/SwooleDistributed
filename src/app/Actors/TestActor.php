<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-1-2
 * Time: 下午3:24
 */

namespace app\Actors;


use Server\CoreBase\Actor;

class TestActor extends Actor
{
    public function test1()
    {
        yield $this->redis_pool->getCoroutine()->set("test", 199);
        return 1;
    }

    public function test2()
    {
        $result = yield $this->redis_pool->getCoroutine()->get("test");
        return $result;
    }

    public function test3()
    {
        $result = yield $this->redis_pool->getCoroutine()->del("test");
        return "3";
    }
    /**
     * 处理注册状态
     * @param $key
     * @param $value
     */
    public function registStatusHandle($key, $value)
    {
        /* switch ($key) {
             case 'status':
                 switch ($value) {
                     case 1:
                         $this->tick(100, function () {
                             echo "1\n";
                         });
                         break;
                 }
                 break;
         }*/
    }
}