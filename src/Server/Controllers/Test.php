<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-3-19
 * Time: 上午10:07
 */

namespace Server\Controllers;


use Server\CoreBase\Actor;
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
    public function http_actor()
    {
        $actor = yield Actor::create(TestActor::class,"test");
        $this->http_output->end(1);
    }
    public function http_a()
    {
        $result = yield Actor::getRpc("test")->test();
        $this->http_output->end($result);
    }
}
class TestActor extends Actor{

    /**
     * 处理注册状态
     * @param $key
     * @param $value
     */
    public function registStatusHandle($key, $value)
    {
        // TODO: Implement registStatusHandle() method.
    }
    public function test()
    {
        throw new \Exception(112);
    }
}