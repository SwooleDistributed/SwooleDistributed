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

class MySqlCoroutine extends CoroutineBase
{

    public function __construct()
    {
        parent::__construct();
    }

    public function send($callback)
    {
        // TODO: Implement send() method.
    }

    public function setRequest($sql)
    {
        $this->request = "[sql].$sql";
    }

    public function onTimeOut()
    {
        if (empty($this->downgrade)) {
            $result = new SwooleException("[CoroutineTask]: Time Out!, [Request]: $this->request");
        } else {
            $result = \co::call_user_func($this->downgrade);
        }
        $result = $this->getResult($result);
        return $result;
    }
}