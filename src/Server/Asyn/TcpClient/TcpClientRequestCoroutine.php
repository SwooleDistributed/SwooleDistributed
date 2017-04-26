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

class TcpClientRequestCoroutine extends CoroutineBase
{
    /**
     * @var TcpClientPool
     */
    public $pool;
    public $data;
    public $oneway;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 对象池模式代替__construct
     * @param $pool
     * @param $data
     * @param $oneway
     * @return $this
     * @throws SwooleException
     */
    public function init($pool, $data, $oneway=false)
    {
        $this->pool = $pool;
        $this->data = $data;
        $this->oneway = $oneway;
        if (!array_key_exists('path', $data)) {
            throw new SwooleException('tcp data must has path');
        }
        $this->request = '[tcpClient]' .$pool->connect. $data['path'];
        unset($this->data['path']);
        if ($this->fuse()) {//启动断路器
            $this->send(function ($result) {
                $this->result = $result;
                $this->immediateExecution();
            });
        }
        return $this;
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