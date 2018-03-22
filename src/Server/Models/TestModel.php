<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-3-21
 * Time: 下午5:22
 */

namespace Server\Models;


use Server\CoreBase\Model;

class TestModel extends Model
{
    public function test1()
    {
        $this->redis_pool->getCoroutine()->get("key");
    }
}