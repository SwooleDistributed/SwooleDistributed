<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-15
 * Time: 上午10:52
 */

namespace app\Process;

use Server\Asyn\Mysql\MysqlAsynPool;
use Server\Asyn\Redis\RedisAsynPool;
use Server\Components\Process\Process;

class MyProcess extends Process
{
    protected $redisPool;
    protected $mysqlPool;

    public function start($process)
    {
        $this->redisPool = new RedisAsynPool($this->config, $this->config->get('redis.active'));
        $this->mysqlPool = new MysqlAsynPool($this->config, $this->config->get('mysql.active'));
        get_instance()->addAsynPool("redisPool", $this->redisPool);
        get_instance()->addAsynPool("mysqlPool", $this->mysqlPool);
    }

    public function getData()
    {
        return '123';
    }

    protected function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }
}