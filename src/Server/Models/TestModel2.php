<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-3-21
 * Time: 下午5:22
 */

namespace Server\Models;


use Server\CoreBase\Model;

class TestModel2 extends Model
{
    public function test()
    {
        $test = $this->loader->model("TestModel",$this);
        return $test->test1();
    }
    public function test2()
    {
        $test = $this->loader->model("TestModel",$this);
        return $test->test1();
    }
}