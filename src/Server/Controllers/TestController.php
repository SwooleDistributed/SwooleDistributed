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
        $this->sendToUids([1,2,3,4,5], ['a'=>$this->client_data->data]);
    }
    public function bind_uid(){
        get_instance()->bindUid($this->fd, $this->client_data->data);
    }
}