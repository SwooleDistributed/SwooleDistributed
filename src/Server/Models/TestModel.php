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
    public function test_mysql()
    {
        $result = $this->db->select("*")->from("account")->limit(1)->query();
        return $result->getResult();
    }
}