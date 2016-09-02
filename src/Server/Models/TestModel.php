<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午1:44
 */

namespace Server\Models;


use Server\CoreBase\Model;
use Server\Tasks\TestTask;

class TestModel extends Model
{
    public function test(){
        return 123456;
    }
    public function test_coroutine()
    {
        $mySqlCoroutine = $this->mysql_pool->dbQueryBuilder->select('*')->from('account')->where('uid', 10303)->coroutineSend();
        $result = yield $mySqlCoroutine;
        $redisCoroutine = $this->redis_pool->coroutineSend('get', 'test');
        $result = yield $redisCoroutine;
        return $result;
    }
}