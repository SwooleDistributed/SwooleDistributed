<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-3-19
 * Time: 上午10:07
 */

namespace Server\Controllers;

use Server\CoreBase\Actor;
use Server\CoreBase\ChildProxy;
use Server\CoreBase\Controller;

class Test extends Controller
{
    public function __construct(string $proxy = ChildProxy::class)
    {
        parent::__construct($proxy);
    }

    public function http_error()
    {
        throw new \Exception("test");
    }

    public function http_redis()
    {
        $this->redis->incr("test");
    }

    public function http_createActor()
    {
        Actor::create(TestActor::class, "test");
    }

    public function http_actor()
    {
        $a = Actor::getRpc("test");
        $a->test();
    }

    public function login($uid)
    {
        $this->bindUid($uid);
        $this->send("ok");
    }

    public function mySub($topic)
    {
        $this->addSub($topic);
        $this->send("ok");
    }

    public function myPub($topic)
    {
        $this->sendPub($topic, "hello");
        $this->send("ok");
    }
}
