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
    public function http_error()
    {
        throw new \Exception("test");
    }

    public function http_test()
    {
        $test = $this->loader->model("TestModel2", $this);
        $test->test();
        $test->test2();
        $this->http_output->end($this->getContext());

    }

    public function http_actor()
    {
        $a = Actor::getRpc("test");
        $a->test();
    }

    public function http_redis()
    {
        $this->redis->get("a");
    }

    public function http_mysql()
    {
        $this->db->begin(function ($client){
            $testModel = $this->loader->model("TestModel", $this);
        });
    }

}
