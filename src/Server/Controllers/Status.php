<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-9
 * Time: 上午11:29
 */

namespace Server\Controllers;
use Server\Components\Consul\ConsulHelp;
use Server\CoreBase\Controller;
use Server\SwooleMarco;

/**
 * SD状态控制器
 * 返回SD的运行状态
 * Class StatusController
 * @package Server\Controllers
 */
class Status extends Controller
{

    public function defaultMethod()
    {
        $status = get_instance()->server->stats();
        $status['now_task'] = get_instance()->getServerAllTaskMessage();
        $this->http_output->end($status);
    }
}