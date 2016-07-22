<?php
namespace Server\Tasks;

use Server\CoreBase\SwooleException;
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
    public function test($data)
    {
        $sum = 0;
        for ($i = 0; $i < 10000; $i++) {
            $sum += $i;
        }
        return $data;
    }

    public function testTimer()
    {
        
    }

}