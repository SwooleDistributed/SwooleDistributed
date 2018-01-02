<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午1:44
 */

namespace app\Models;


use Server\Components\CatCache\TimerCallBack;
use Server\CoreBase\ChildProxy;
use Server\CoreBase\Model;
use Server\CoreBase\SwooleException;

class TestModel extends Model
{

    public function __construct()
    {
        parent::__construct(TestModelProxy::class);
    }

    public function initialization(&$context)
    {
        parent::initialization($context);
    }

    public function timerTest()
    {
        print_r("model timer\n");
    }

    public function contextTest()
    {
        $testTask = $this->loader->task('TestTask', $this);
        $testTask->contextTest();
        $testTask->startTask(null);
    }

    public function test_coroutine()
    {
        $redisCoroutine = $this->redis_pool->coroutineSend('get', 'test');
        $result = yield $redisCoroutine;
        return $result;
    }

    public function test_coroutineII($callback)
    {
        $this->redis_pool->get('test', function ($uid) use ($callback) {
            $this->mysql_pool->dbQueryBuilder->select('*')->from('account')->where('uid', $uid);
            $this->mysql_pool->query(function ($result) use ($callback) {
                call_user_func($callback, $result);
            });
        });
    }

    public function test_exception()
    {
        $result = yield $this->redis_pool->coroutineSend('get', 'test');
        throw new SwooleException('test');
    }

    public function test_exceptionII()
    {
        try {
            yield $this->test_exception();
        } catch (\Exception $e) {
            print_r(1);
        }
    }

    public function test_task()
    {
        $testTask = $this->loader->task('TestTask', $this);
        $testTask->test();
        $testTask->startTask(null);
    }

    public function test_pdo()
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from('account')->where('uid', 36)->coroutineSend();
        $result = yield $this->mysql_pool->dbQueryBuilder->update('account')->where('uid', 36)->set(['status' => 1])->coroutineSend();
        $result = yield $this->mysql_pool->dbQueryBuilder->replace('account')->where('uid', 91)->set(['status' => 1])->coroutineSend();
        print_r($result);
    }

    public function testRedis()
    {
        $result = yield $this->redis_pool->getCoroutine()->get('test');
        return 1;
    }

    public function testMysql()
    {
        return $this->mysql_pool->dbQueryBuilder->select('*')->from('account')->coroutineSend();
    }

    public function testWhile()
    {
        while (1) {

        }
    }

    public function testTimerCall($value, $token)
    {
        var_dump($token);
        TimerCallBack::ack($token);
    }

}

class TestModelProxy extends ChildProxy
{
    public function test_exception()
    {
        $this->beforeCall("test_exception");
        return $this->own->test_exception();
    }
}