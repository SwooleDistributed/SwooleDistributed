<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-3-8
 * Time: 下午2:36
 */

namespace Server\Asyn\Mysql;

use Server\Asyn\IAsynPool;
use Server\CoreBase\SwooleException;
use Server\Memory\Pool;

class MysqlAsynPool implements IAsynPool
{
    const AsynName = 'mysql';
    protected $pool_chan;
    protected $mysql_arr;
    private $active;
    protected $config;
    /**
     * @var Miner
     */
    protected $mysql_client;
    /**
     * @var Miner
     */
    public $dbQueryBuilder;
    private $client_max_count;

    public function __construct($config, $active)
    {
        $this->active = $active;
        $this->config = get_instance()->config;
        $this->client_max_count = $this->config->get('mysql.asyn_max_count', 10);
        if (get_instance()->isTaskWorker()) return;
        $this->pool_chan = new \chan($this->client_max_count);
        for ($i = 0; $i < $this->client_max_count; $i++) {
            $client = new \Swoole\Coroutine\MySQL();
            $client->id = $i;
            $this->pushToPool($client);
        }
    }

    /**
     * @return mixed
     */
    public function getActveName()
    {
        return $this->active;
    }
    /**
     * @return Miner
     */
    public function installDbBuilder()
    {
        $this->dbQueryBuilder = Pool::getInstance()->get(Miner::class)->setPool($this);
        return $this->dbQueryBuilder;
    }

    public function begin(callable $fuc, callable $errorFuc)
    {
        $client = $this->pool_chan->pop();
        if (!$client->connected) {
            $set = $this->config['mysql'][$this->active];
            $result = $client->connect($set);
            if (!$result) {
                $this->pool_chan->push($client);
                throw new SwooleException($client->connect_error);
            }
        }
        $client->query("begin");
        $result = null;
        try {
            $this->dbQueryBuilder->setClient($client);
            $result = $fuc($client);
            $client->query("commit");
        } catch (\Throwable $e) {
            $client->query("rollback");
            if ($errorFuc != null) $result = $errorFuc($client);
        } finally {
            $this->dbQueryBuilder->setClient(null);
        }
        $this->pushToPool($client);
        return $result;
    }

    /**
     * @param $sql
     * @param null $client
     * @param MySqlCoroutine $mysqlCoroutine
     * @return mixed
     * @throws SwooleException
     */
    public function query($sql, $client = null, MySqlCoroutine $mysqlCoroutine)
    {
        $notPush = false;
        $delayRecv = $mysqlCoroutine->getDelayRecv();
        if ($client == null) {
            $client = $this->pool_chan->pop();
            $client->setDefer($delayRecv);
        } else {//这里代表是事务
            $notPush = true;
            //事务不允许setDefer
            $delayRecv = false;
            $client->setDefer($delayRecv);
        }
        if (!$client->connected) {
            $set = $this->config['mysql'][$this->active];
            $result = $client->connect($set);
            if (!$result) {
                $this->pushToPool($client);
                $result = $mysqlCoroutine->getResult(new SwooleException("[err]:$client->connect_error"));
                $mysqlCoroutine->destroy();
                return $result;
            }
        }
        $res = $client->query($sql, $mysqlCoroutine->getTimeout() / 1000);
        if ($res === false) {
            if ($client->errno == 110) {
                $result = $mysqlCoroutine->onTimeOut();
            } else {
                $result = $mysqlCoroutine->getResult(new SwooleException("[sql]:$sql,[err]:$client->error"));
            }
            $mysqlCoroutine->destroy();
            return $result;
        }
        $mysqlCoroutine->destroy();
        if ($delayRecv)//延迟收包
        {
            $data['delay_recv_fuc'] = function () use ($client) {
                $res = $client->recv();
                $data['result'] = $res;
                $data['affected_rows'] = $client->affected_rows;
                $data['insert_id'] = $client->insert_id;
                $data['client_id'] = $client->id;
                $this->pushToPool($client);
                return $data;
            };
            return new MysqlSyncHelp($sql, $data);
        }
        $data['result'] = $res;
        $data['affected_rows'] = $client->affected_rows;
        $data['insert_id'] = $client->insert_id;
        $data['client_id'] = $client->id;
        if (!$notPush) {
            $this->pushToPool($client);
        }
        return new MysqlSyncHelp($sql, $data);
    }

    /**
     * @param $sql
     * @param $statement
     * @param $holder
     * @param null $client
     * @param MySqlCoroutine $mysqlCoroutine
     * @return mixed
     * @throws SwooleException
     */
    public function prepare($sql, $statement, $holder, $client = null, MySqlCoroutine $mysqlCoroutine)
    {
        //暂不支持----------
        $delayRecv = false;
        //-----------------
        $notPush = false;
        if ($client == null) {
            $client = $this->pool_chan->pop();
            $client->setDefer($delayRecv);
        } else {
            $notPush = true;
            //事务不允许setDefer
            $delayRecv = false;
            $client->setDefer($delayRecv);
        }
        if (!$client->connected) {
            $set = $this->config['mysql'][$this->active];
            $result = $client->connect($set);
            if (!$result) {
                $this->pushToPool($client);
                throw new SwooleException($client->connect_error);
            }
        }
        $res = $client->prepare($statement);
        if ($res != false) {
            $res = $res->execute($holder, $mysqlCoroutine->getTimeout() / 1000);
        }
        if ($res === false) {
            if ($client->errno == 110) {
                $result = $mysqlCoroutine->onTimeOut();
            } else {
                $result = $mysqlCoroutine->getResult(new SwooleException("[sql]:$sql,[err]:$client->error"));
            }
            $mysqlCoroutine->destroy();
            return $result;
        }
        $mysqlCoroutine->destroy();
        if ($delayRecv)//延迟收包
        {
            $data['delay_recv_fuc'] = function () use ($client) {
                $res = $client->recv();
                $data['result'] = $res;
                $data['affected_rows'] = $client->affected_rows;
                $data['insert_id'] = $client->insert_id;
                $data['client_id'] = $client->id;
                $this->pushToPool($client);
                return $data;
            };
            return new MysqlSyncHelp($sql, $data);
        }
        $data['result'] = $res;
        $data['affected_rows'] = $client->affected_rows;
        $data['insert_id'] = $client->insert_id;
        $data['client_id'] = $client->id;
        if (!$notPush) {
            $this->pushToPool($client);
        }
        return new MysqlSyncHelp($sql, $data);
    }

    public function getAsynName()
    {
        return self::AsynName . ":" . $this->active;
    }

    public function pushToPool($client)
    {
        $this->pool_chan->push($client);
    }

    public function getSync()
    {
        if ($this->mysql_client != null) return $this->mysql_client;
        $activeConfig = $this->config['mysql'][$this->active];
        $this->mysql_client = new Miner();
        $this->mysql_client->pdoConnect($activeConfig);
        return $this->mysql_client;
    }
}