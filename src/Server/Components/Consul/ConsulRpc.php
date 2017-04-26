<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-13
 * Time: 下午1:46
 */

namespace Server\Components\Consul;


use Server\Asyn\TcpClient\SdTcpRpcPool;

class ConsulRpc extends SdTcpRpcPool
{
    private $service;
    private $context;

    /**
     * @param $service
     * @param $context
     * @return $this
     */
    public function init($service, $context)
    {
        $this->service = $service;
        $this->context = $context;
        return $this;
    }

    /**
     * @param $name
     * @param $arguments
     * @return \Server\Asyn\TcpClient\TcpClientRequestCoroutine
     */
    public function __call($name, $arguments)
    {
        $sendData = $this->helpToBuildSDControllerQuest($this->context, $this->service, $name);
        $sendData['params'] = $arguments;
        return $this->coroutineSend($sendData);
    }

    /**
     * @param $name
     * @param $arguments
     * @param $oneway
     * @return \Server\Asyn\TcpClient\TcpClientRequestCoroutine
     */
    public function call($name,$arguments,$oneway)
    {
        $sendData = $this->helpToBuildSDControllerQuest($this->context, $this->service, $name);
        $sendData['params'] = $arguments;
        return $this->coroutineSend($sendData,$oneway);
    }
}