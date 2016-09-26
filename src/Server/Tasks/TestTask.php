<?php
namespace Server\Tasks;

use Server\CoreBase\Task;
use Server\DataBase\DbConnection;
use Server\DataBase\DbQueryBuilder;

/**
 * Created by PhpStorm.
 * User: tmtbe
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
        print_r("test\n");
        return 123;
    }
}