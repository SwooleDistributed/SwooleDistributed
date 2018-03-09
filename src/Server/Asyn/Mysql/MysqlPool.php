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

class MysqlPool implements IAsynPool
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
    protected $dbQueryBuilder;
    private $client_max_count;

    public function __construct($config, $active)
    {
        $this->active = $active;
        $this->config = get_instance()->config;
        $this->client_max_count = $this->config->get('mysql.asyn_max_count', 10);
        $this->pool_chan = new \chan($this->client_max_count);
        for ($i = 0; $i < $this->client_max_count; $i++) {
            $client = new \Swoole\Coroutine\MySQL();
            $this->pool_chan->push($client);
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

    public function begin(callable $fuc)
    {
        $client = $this->pool_chan->pop();
        $client->query("begin");
        try {
            $this->dbQueryBuilder->setClient($client);
            $fuc($this->dbQueryBuilder);
            $client->query("commit");
        } catch (\Exception $e) {
            $client->query("rollback");
        } finally {
            $this->dbQueryBuilder->setClient(null);
        }
    }

    public function query($sql, $timeOut = -1, $client = null)
    {
        if ($client == null) {
            $client = $this->pool_chan->pop();
        }
        if (!$client->connected) {
            $set = $this->config['mysql'][$this->active];
            $result = $client->connect($set);
            if (!$result) {
                $this->pool_chan->push($client);
                throw new SwooleException($client->connect_error);
            }
        }
        var_dump($sql);
        $res = $client->query($sql, $timeOut);
        if ($res === false) {
            if ($client->errno == 110) {
                throw new SwooleException("[CoroutineTask]: Time Out!, [Request]: $sql");
            } else {
                throw new SwooleException("[sql]:$sql,[err]:$client->error");
            }
        }
        $this->pool_chan->push($client);
        $data['result'] = $res;
        $data['affected_rows'] = $client->affected_rows;
        $data['insert_id'] = $client->insert_id;
        return $data;
    }

    /**
     * @param $sql
     * @param $statement
     * @param $holder
     * @param null $client
     * @return mixed
     * @throws SwooleException
     */
    public function prepare($sql, $statement, $holder, $client = null)
    {
        if ($client == null) {
            $client = $this->pool_chan->pop();
        }
        if (!$client->connected) {
            $set = $this->config['mysql'][$this->active];
            $result = $client->connect($set);
            if (!$result) {
                $this->pool_chan->push($client);
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
                throw new SwooleException($client->error);
            }
        }
        $this->pool_chan->push($client);
        $data['result'] = $res;
        $data['affected_rows'] = $client->affected_rows;
        $data['insert_id'] = $client->insert_id;
        return $data;
    }

    function getAsynName()
    {
        return self::AsynName . ":" . $this->active;
    }

    function getSync()
    {
        if ($this->mysql_client != null) return $this->mysql_client;
        $activeConfig = $this->config['mysql'][$this->active];
        $this->mysql_client = new Miner();
        $this->mysql_client->pdoConnect($activeConfig);
        return $this->mysql_client;
    }

    function pushToPool($client)
    {
        // TODO: Implement pushToPool() method.
    }
}