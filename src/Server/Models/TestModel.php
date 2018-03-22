<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-3-22
 * Time: 下午4:24
 */

namespace Server\Models;


use Server\CoreBase\Model;

class TestModel extends Model
{
    public function test()
    {
        $testModel2 = $this->loader->model("TestModel2",$this);
        $testModel2->test();
        return 1;
    }
}