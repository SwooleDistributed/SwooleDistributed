<?php
namespace app;

use Server\Asyn\HttpClient\HttpClientPool;
use Server\Asyn\TcpClient\SdTcpRpcPool;
use Server\Asyn\TcpClient\TcpClientPool;
use Server\SwooleDistributedServer;

/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-19
 * Time: 下午2:36
 */
class AppServer extends SwooleDistributedServer
{
    /**
     * 开服初始化(支持协程)
     * @return mixed
     */
    public function onOpenServiceInitialization()
    {
        parent::onOpenServiceInitialization();
    }

    /**
     * 当一个绑定uid的连接close后的清理
     * 支持协程
     * @param $uid
     */
    public function onUidCloseClear($uid)
    {
        // TODO: Implement onUidCloseClear() method.
    }

    /**
     * 这里可以进行额外的异步连接池，比如另一组redis/mysql连接
     * @return array
     */
    public function initAsynPools()
    {
        parent::initAsynPools();
        //$this->addAsynPool('redis2', new RedisAsynPool($this->config, 'test2'));
        $this->addAsynPool('httpClient', new HttpClientPool($this->config, 'http://192.168.8.48:8081'));
        $this->addAsynPool('tcpClient', new TcpClientPool($this->config, '192.168.8.48:9093'));
        $this->addAsynPool('rpc', new SdTcpRpcPool($this->config, '192.168.8.48:9093'));
    }
}