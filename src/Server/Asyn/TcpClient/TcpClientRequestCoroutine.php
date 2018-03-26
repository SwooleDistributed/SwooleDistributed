<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\Asyn\TcpClient;

use Server\CoreBase\SwooleException;
use Server\Coroutine\CoroutineBase;
use Server\Memory\Pool;
use Server\Start;

class TcpClientRequestCoroutine extends CoroutineBase
{
    /**
     * @var TcpClientPool
     */
    public $pool;
    public $data;
    public $oneway;

    /**
     * 对象池模式代替__construct
     * @param $pool
     * @param $data
     * @param bool $oneway
     * @param $set
     * @return $this
     * @throws SwooleException
     */
    public function init($pool, $data, $oneway = false, $set)
    {
        $this->pool = $pool;
        $this->data = $data;
        $this->oneway = $oneway;
        if (!array_key_exists('path', $data)) {
            throw new SwooleException('tcp data must has path');
        }
        $d = "[".$pool->connect."]"."[". $data['path'] ."]";
        $this->request = "[tcpClient]$d";
        if (Start::getDebug()){
            secho("TCP",$d);
        }
        unset($this->data['path']);
        $this->set($set);
        if ($this->fuse()) {//启动断路器
            $this->send(function ($result) {
                $this->coPush($result);
            });
        }
        return $this->returnInit();
    }

    public function send($callback)
    {
        $this->token = $this->pool->send($this->data, $callback, $this->oneway);
    }

    public function destroy()
    {
        parent::destroy();
        $this->pool->removeTokenCallback($this->token);
        $this->data = null;
        $this->pool = null;
        $this->token = null;
        Pool::getInstance()->push($this);
    }

    protected function onTimerOutHandle()
    {
        parent::onTimerOutHandle();
        $this->pool->destoryGarbage($this->token);
    }
}