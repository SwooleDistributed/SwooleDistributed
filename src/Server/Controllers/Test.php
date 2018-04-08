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

    public function http_redis()
    {
        $this->redis_pool->getCoroutine()->incr("test");
    }

    public function http_createActor()
    {
        Actor::create(TestActor::class,"test");
    }
    public function http_actor()
    {
        $a = Actor::getRpc("test");
        $a->test();
    }
    public function login($uid)
    {
        $this->bindUid($uid);
    }
    public function mySub($topic)
    {
        $this->sub($topic);
    }
    public function myPub($topic)
    {
        $this->sendPub($topic,"hello");
    }
}
