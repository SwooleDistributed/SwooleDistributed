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
    protected $resultHandle;

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
            if (!$this->noException) {
                $this->isFaile = true;
                $ex = new SwooleException($result['error']);
                $this->destroy();
                throw $ex;
            } else {
                $this->result = $this->noExceptionReturn;
            }
        }
        if ($this->resultHandle != null && is_array($result)) {
            $result = call_user_func($this->resultHandle, $result);
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

    /**
     * 注册结果处理函数
     * @param callable $handle
     */
    protected function registResultFuc(callable $handle)
    {
        $this->resultHandle = $handle;
    }


    /**
     * @return $this
     */
    public function result_array()
    {
        $this->resultHandle = function ($result) {
            return $result['result'];
        };
        return $this;
    }

    /**
     * 返回某一个
     * @param $index
     * @return $this
     */
    public function row_array($index)
    {
        $this->resultHandle = function ($result) use ($index) {
            return $result['result'][$index] ?? null;
        };
        return $this;
    }

    /**
     * 返回一个
     * @return $this
     */
    public function row()
    {
        $this->resultHandle = function ($result) {
            return $result['result'][0] ?? null;
        };
        return $this;
    }

    /**
     * 返回数量
     * @return $this
     */
    public function num_rows()
    {
        $this->resultHandle = function ($result) {
            return count($result['result']);
        };
        return $this;
    }

    /**
     * @return $this
     */
    public function insert_id()
    {
        $this->resultHandle = function ($result) {
            return $result['insert_id'];
        };
        return $this;
    }
}