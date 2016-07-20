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
    public function test(){
        $this->testTask = $this->loader->task('TestTask');
        $this->testTask->test(123);
        $this->testTask->startTask(function ($serv, $task_id, $data) {
            $this->send(123);
        });
    }
}