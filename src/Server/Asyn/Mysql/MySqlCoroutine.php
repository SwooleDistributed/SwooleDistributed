<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\Asyn\Mysql;

use Server\CoreBase\SwooleException;
use Server\Coroutine\CoroutineBase;
use Server\Memory\Pool;

class MySqlCoroutine extends CoroutineBase
{
    /**
     * @var MysqlAsynPool
     */
    public $mysqlAsynPool;
    public $bind_id;
    public $sql;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 对象池模式代替__construct
     * @param $_mysqlAsynPool
     * @param null $_bind_id
     * @param null $_sql
     * @return $this
     */
    public function init($_mysqlAsynPool, $_bind_id = null, $_sql = null)
    {
        $this->mysqlAsynPool = $_mysqlAsynPool;
        $this->bind_id = $_bind_id;
        $this->sql = $_sql;
        $this->request = '#Mysql:' . $_sql;
        $this->send(function ($result) {
            $this->result = $result;
            $this->immediateExecution();
        });
        return $this;
    }
    public function send($callback)
    {
        $this->token = $this->mysqlAsynPool->query($callback, $this->bind_id, $this->sql);
    }

    public function getResult()
    {
        $result = parent::getResult();
        if (is_array($result) && isset($result['error'])) {
            throw new SwooleException($result['error']);
        }
        return $result;
    }

    public function destroy()
    {
        parent::destroy();
        if ($this->mysqlAsynPool != null) {
            $this->mysqlAsynPool->removeTokenCallback($this->token);
        }
        $this->token = null;
        $this->mysqlAsynPool = null;
        $this->bind_id = null;
        $this->sql = null;
        Pool::getInstance()->push($this);
    }

    protected function onTimerOutHandle()
    {
        parent::onTimerOutHandle();
        if ($this->mysqlAsynPool != null) {
            $this->mysqlAsynPool->destoryGarbage($this->token);
        }
    }
}