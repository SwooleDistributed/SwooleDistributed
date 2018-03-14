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
        try {
            $this->dbQueryBuilder->setClient($client);
            $fuc();
            $client->query("commit");
        } catch (\Throwable $e) {
            $client->query("rollback");
            if ($errorFuc != null) $errorFuc();
        } finally {
            $this->dbQueryBuilder->setClient(null);
        }
        $this->pushToPool($client);
    }

    public function query($sql, $timeOut = -1, $client = null)
    {
        $notPush = false;
        if ($client == null) {
            $client = $this->pool_chan->pop();
        } else {
            $notPush = true;
        }
        if (!$client->connected) {
            $set = $this->config['mysql'][$this->active];
            $result = $client->connect($set);
            if (!$result) {
                $this->pushToPool($client);
                throw new SwooleException($client->connect_error);
            }
        }
        $res = $client->query($sql, $timeOut);
        if ($res === false) {
            if ($client->errno == 110) {
                throw new SwooleException("[CoroutineTask]: Time Out!, [Request]: $sql");
            } else {
                throw new SwooleException("[sql]:$sql,[err]:$client->error");
            }
        }
        if (!$notPush) {
            $this->pushToPool($client);
        }
        $data['result'] = $res;
        $data['affected_rows'] = $client->affected_rows;
        $data['insert_id'] = $client->insert_id;
        $data['client_id'] = $client->id;
        return $data;
    }

    /**
     * @param $sql
     * @param $statement
     * @param $holder
     * @param null $client
     * @return mixed
     * @throws SwooleException
     * @internal param $sql
     * @internal param int $timerOut
     */
    public function prepare($sql, $statement, $holder, $client = null)
    {
        $notPush = false;
        if ($client == null) {
            $client = $this->pool_chan->pop();
        } else {
            $notPush = true;
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
            $res = $res->execute($holder);
        }
        if ($res === false) {
            if ($client->errno == 110) {
                throw new SwooleException("[CoroutineTask]: Time Out!, [Request]: $sql");
            } else {
                throw new SwooleException("[sql]:$sql,[err]:$client->error");
            }
        }
        if (!$notPush) {
            $this->pushToPool($client);
        }
        $data['result'] = $res;
        $data['affected_rows'] = $client->affected_rows;
        $data['insert_id'] = $client->insert_id;
        $data['client_id'] = $client->id;
        return $data;
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
        if ($this->mysql_client!=null) return $this->mysql_client;
        $activeConfig = $this->config['mysql'][$this->active];
        $this->mysql_client = new Miner();
        $this->mysql_client->pdoConnect($activeConfig);
        return $this->mysql_client;
    }
}