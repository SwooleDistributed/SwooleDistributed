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
    public function test()
    {
        var_dump(2);
        $this->tick(1000, function () {
            var_dump("test");
        });
    }

    public function destroy()
    {
        var_dump("destory");
        parent::destroy();
    }
}