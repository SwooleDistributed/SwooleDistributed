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
    public $token;

    public function __construct()
    {
        parent::__construct();
    }

    public function init($pool, $data)
    {
        $this->pool = $pool;
        $this->data = $data;
        $this->request = '[httpClient]' . $data['path'];
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

    public function destory()
    {
        parent::destory();
        unset($this->pool);
        unset($this->data);
        unset($this->token);
        Pool::getInstance()->push(HttpClientRequestCoroutine::class, $this);
    }

    protected function onTimerOutHandle()
    {
        parent::onTimerOutHandle();
        $this->pool->destoryGarbage($this->token);
    }
}