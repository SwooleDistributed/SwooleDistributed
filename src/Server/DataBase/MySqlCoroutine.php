<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\DataBase;


use Server\CoreBase\CoroutineNull;
use Server\CoreBase\ICoroutineBase;

class MySqlCoroutine implements ICoroutineBase
{
    /**
     * @var MysqlAsynPool
     */
    public $mysqlAsynPool;
    public $bind_id;
    public $sql;
    public $result;

    public function __construct($_mysqlAsynPool, $_bind_id = null, $_sql = null)
    {
        $this->result = CoroutineNull::getInstance();
        $this->mysqlAsynPool = $_mysqlAsynPool;
        $this->bind_id = $_bind_id;
        $this->sql = $_sql;
        $this->send(function ($result) {
            $this->result = $result;
        });
    }

    public function send($callback)
    {
        $this->mysqlAsynPool->query($callback, $this->bind_id, $this->sql);
    }

    public function getResult()
    {
        return $this->result;
    }
}