<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-1-4
 * Time: 上午11:11
 */

namespace test;


use Server\CoreBase\Actor;
use Server\Test\TestCase;

/**
 * 服务器框架Actor测试用例
 * @package test
 */
class ServerActorTest extends TestCase
{

    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     */
    public function setUpBeforeClass()
    {
        for ($i = 0; $i < 100; $i++) {
            Actor::create(TestActor::class, "test_" . $i);
        }
    }

    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     * @throws \Exception
     */
    public function tearDownAfterClass()
    {
        for ($i = 0; $i < 100; $i++) {
            Actor::destroyActor("test_" . $i);
        }
    }

    /**
     * 测试类的每个测试方法都会运行一次 setUp() 和 tearDown() 模板方法
     */
    public function setUp()
    {
        // TODO: Implement setUp() method.
    }

    /**
     * 测试类的每个测试方法都会运行一次 setUp() 和 tearDown() 模板方法
     */
    public function tearDown()
    {
        // TODO: Implement tearDown() method.
    }

    /**
     * @throws \Server\Test\SwooleTestException
     * @throws \Exception
     */
    public function testActorHas()
    {
        for ($i = 0; $i < 100; $i++) {
            $has = Actor::has("test_" . $i);
            $this->assertTrue($has);
        }

    }

    /**
     * @throws \Server\Test\SwooleTestException
     */
    public function testActorRpc()
    {
        $data = [];
        for ($j = 0; $j < 100; $j++) {
            for ($i = 0; $i < 100; $i++) {
                $rpc = Actor::getRpc("test_" . 1);
                $data[] = $rpc->test1();
                $data[] = $rpc->test2();
            }
        }
        $this->assertCount(20000, $data);
    }

    /**
     * @throws \Server\Test\SwooleTestException
     */
    public function testActorRpcBegin()
    {
        $data = [];
        for ($j = 0; $j < 100; $j++) {
            for ($i = 0; $i < 100; $i++) {
                $rpc = Actor::getRpc("test_" . $i);
                $rpc->beginCo(function () use ($rpc, &$data) {
                    $data[] = $rpc->test1();
                    $data[] = $rpc->test2();
                });
            }
        }
        $this->assertCount(20000, $data);
    }
}

class TestActor extends Actor
{

    /**
     * 处理注册状态
     * @param $key
     * @param $value
     */
    public function registStatusHandle($key, $value)
    {
        // TODO: Implement registStatusHandle() method.
    }

    /**
     * @return int
     */
    public function test1()
    {
        $this->saveContext["test"] = 1;
        $this->redis->set("test",1);
        return 1;
    }

    public function test2()
    {
        $result = $this->saveContext["test"];
        $this->after(1000,function (){
            $this->redis->get("test");
        });
        return $result;
    }
}