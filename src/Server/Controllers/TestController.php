<?php
namespace Server\Controllers;

use Server\CoreBase\Controller;
use Server\Tasks\TestTask;

/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午3:51
 */
class TestController extends Controller
{
    /**
     * @var TestTask
     */
    public $testTask;

    public function test()
    {
        $this->mysql_pool->dbQueryBuilder->select("*")->where('uid', 10004)->from('account')->get(function ($result) {
            var_dump($result);
        });
        $this->send('test');
    }

    public function bind_uid()
    {
        get_instance()->bindUid($this->fd, $this->client_data->data);
    }

    /**
     * 效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function efficiency_test()
    {
        $data = $this->client_data->data;
        $this->sendToUid(mt_rand(1,100), $data);
    }

    /**
     * 测试redis效率
     */
    public function ansy_redis_test()
    {
        $this->redis_pool->get('test', function ($result) {
            $this->send($this->client_data->data);
        });
    }
}