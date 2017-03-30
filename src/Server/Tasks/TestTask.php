<?php
namespace Server\Tasks;

use Server\CoreBase\Task;

/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午1:06
 */
class TestTask extends Task
{
    public function testTimer()
    {
        print_r("test timer task\n");
    }

    public function testsend()
    {
        get_instance()->sendToAll(1);
    }

    public function test()
    {
        print_r(date('y-m-d H:i:s', time()) . "\n");
        return 123;
    }

    public function contextTest()
    {
        print_r($this->getContext());
    }

    public function test_task()
    {
        $testModel = $this->loader->model('TestModel', $this);
        $result = yield $testModel->test_task();
        print_r($result);
    }

    public function testPdo()
    {
        $testModel = $this->loader->model('TestModel', $this);
        yield $testModel->test_pdo();
    }

    public function testLong()
    {
        var_dump(time());
        sleep(4);
        var_dump(time());
        return 1;
    }

    /**
     * 测试停止
     */
    public function testStop()
    {
        while (true) {
            sleep(1);
            var_dump(1);
        }
    }

    public function testRedis()
    {
        yield $this->helpRedis();
        yield $this->helpRedis();
        yield $this->helpRedis();
        return 1;
    }

    public function helpRedis()
    {
        $result = yield $this->redis_pool->getCoroutine()->get('test');
        $result = yield $this->redis_pool->getCoroutine()->get('test2');
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from('task')
            ->whereIn('type', [0, 1])->where('status', 1)->coroutineSend();
        yield $this->testMysql();
        return 1;
    }

    public function testMysql()
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from('account')->coroutineSend();
        return $result;
    }
}