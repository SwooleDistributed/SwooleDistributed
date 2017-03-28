<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\Asyn\HttpClient;

use Server\Coroutine\CoroutineBase;
use Server\Memory\Pool;

class HttpClientRequestCoroutine extends CoroutineBase
{
    /**
     * @var HttpClientPool
     */
    public $pool;
    public $data;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 对象池模式代替__construct
     * @param $pool
     * @param $data
     * @return $this
     */
    public function init(HttpClientPool $pool, $data)
    {
        $this->pool = $pool;
        $this->data = $data;
        $this->request = '[httpClient]' . $pool->baseUrl.$data['path'];
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
        $this->token = $this->pool->call($this->data, $callback);
    }

    public function destroy()
    {
        parent::destroy();
        $this->pool->removeTokenCallback($this->token);
        $this->pool = null;
        $this->data = null;
        $this->token = null;
        Pool::getInstance()->push($this);
    }

    protected function onTimerOutHandle()
    {
        parent::onTimerOutHandle();
        $this->pool->destoryGarbage($this->token);
    }
}