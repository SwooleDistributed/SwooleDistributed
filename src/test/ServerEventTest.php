<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-1-4
 * Time: 上午11:11
 */

namespace test;


use Server\Components\CatCache\CatCacheRpcProxy;
use Server\Components\Event\EventCoroutine;
use Server\Components\Event\EventDispatcher;
use Server\Components\TimerTask\Timer;
use Server\CoreBase\Child;
use Server\Test\TestCase;

/**
 * 服务器框架Timer测试用例
 * @package test
 */
class ServerEventTest extends TestCase
{

    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     */
    public function setUpBeforeClass()
    {

    }

    /**
     * setUpBeforeClass() 与 tearDownAfterClass() 模板方法将分别在测试用例类的第一个测试运行之前和测试用例类的最后一个测试运行之后调用。
     */
    public function tearDownAfterClass()
    {

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
     */
    public function testCatCache()
    {
        for ($i=0;$i<100000;$i++) {
            CatCacheRpcProxy::getRpc()->offsetSet("test", $i);
            $result = CatCacheRpcProxy::getRpc()->offsetGet("test");
            $this->assertEquals($result, $i);
        }
    }
    /**
     * @throws \Server\Test\SwooleTestException
     * @throws \Exception
     */
    public function testTimerTick()
    {
        $result = EventDispatcher::getInstance()->addOnceCoroutine("get", function (EventCoroutine $eventCoroutine) {
            $eventCoroutine->setDelayRecv();
        });
        Timer::getInstance()->addTick("test", 1000, function (Child $child) {
            EventDispatcher::getInstance()->dispatch("get", "test");
            Timer::getInstance()->clearTick("test");
        });
        $data = $result->recv();
        $this->assertEquals($data, "test");
    }
}
