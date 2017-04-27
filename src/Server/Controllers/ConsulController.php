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
 * SD微服务组件
 * 配合Consul进行服务更新
 * Class ConsulController
 * @package Server\Controllers
 */
class ConsulController extends Controller
{
    /**
     * 收到消息后广播给所有的worker，更新服务
     */
    public function http_ServiceChange()
    {
        $data = $this->http_input->getRawContent();
        get_instance()->sendToAllWorks(SwooleMarco::CONSUL_SERVICES_CHANGE,$data,ConsulHelp::class."::getMessgae");
        $this->destroy();
    }

}