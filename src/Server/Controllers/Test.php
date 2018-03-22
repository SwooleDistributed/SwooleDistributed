<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-3-19
 * Time: 上午10:07
 */

namespace Server\Controllers;


use Server\CoreBase\Controller;

class Test extends Controller
{
    public function http_ex()
    {
        throw new \Exception("test");
    }
    public function http_error()
    {
        $a = [];
        $a[0];
    }
    public function http_test()
    {
        $testModel=$this->loader->model("TestModel",$this);
        $testModel->test();
        $this->http_output->end(1);
    }
}