<?php
/**
 * mysql 异步客户端连接池
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-22
 * Time: 上午10:19
 */

namespace Server\DataBase;


use Server\CoreBase\SwooleException;
use Server\SwooleMarco;

class MysqlAsynPool extends AsynPool
{
    const AsynName = 'mysql';
    /**
     * @var Miner
     */
    public $dbQueryBuilder;
    /**
     * @var array
     */
    public $bind_pool;
    protected $mysql_max_count = 0;

    public function __construct()
    {
        parent::__construct();
        $this->bind_pool = [];
    }

    /**
     * 作为客户端的初始化
     * @param $worker_id
     */
    public function worker_init($worker_id)
    {
        parent::worker_init($worker_id);
        $this->dbQueryBuilder = new Miner($this);
    }

    /**
     * 执行一个sql语句
     * @param $callback
     * @param $bind_id 绑定的连接id，用于事务
     * @param $sql
     */
    public function query($callback, $bind_id = null, $sql = null)
    {
        if ($sql == null) {
            $sql = $this->dbQueryBuilder->getStatement(false);
        }
        if (empty($sql)) {
            throw new SwooleException('sql empty');
        }
        $data = [
            'sql' => $sql
        ];
        $data['token'] = $this->addTokenCallback($callback);
        if (!empty($bind_id)) {
            $data['bind_id'] = $bind_id;
        }
        //写入管道
        $this->asyn_manager->writePipe($this, $data, $this->worker_id);
    }

    /**
     * 执行mysql命令
     * @param $data
     */
    public function execute($data)
    {
        $client = null;
        $bind_id = $data['bind_id']??null;
        if ($bind_id != null) {//绑定
            $client = $this->bind_pool[$bind_id]['client'];
        }
        if ($client == null) {
            if (count($this->pool) == 0) {//代表目前没有可用的连接
                $this->prepareOne();
                $this->commands->push($data);
                return;
            } else {
                $client = $this->pool->shift();
                if ($bind_id != null) {//添加绑定
                    $client->isAffair = true;
                    $this->bind_pool[$bind_id]['client'] = $client;
                }
            }
        } else {
            if ($client->isAffair) {//如果已经在处理事务了就放进去
                $this->bind_pool[$bind_id]['affairs'][] = $data;
                return;
            }
        }

        $sql = $data['sql'];
        $client->query($sql, function ($client, $result) use ($data) {
            if ($result === false) {
                if ($client->errno == 2006 || $client->errno == 2013) {//断线重连
                    $this->reconnect($client);
                    $this->commands->unshift($data);
                } else {
                    throw new SwooleException("[mysql]:" . $client->error . "[sql]:" . $data['sql']);
                }
            }
            $data['result']['client_id'] = $client->client_id;
            $data['result']['result'] = $result;
            $data['result']['affected_rows'] = $client->affected_rows;
            $data['result']['insert_id'] = $client->insert_id;
            unset($data['sql']);
            //给worker发消息
            $this->asyn_manager->sendMessageToWorker($this, $data);
            //不是绑定的连接就回归连接
            if (!isset($data['bind_id'])) {
                $this->pushToPool($client);
            } else {//事务
                $bind_id = $data['bind_id'];
                $client->isAffair = false;
                $affair = array_shift($this->bind_pool[$bind_id]['affairs']);
                if ($affair != null) {
                    $this->execute($affair);
                } else {
                    $this->free_bind($bind_id);
                }
            }
        });
    }

    /**
     * 重连或者连接
     * @param null $client
     */
    public function reconnect($client = null)
    {
        if ($client == null) {
            $client = new \swoole_mysql();
        }
        $set = $this->config['database'][$this->config['database']['active']];
        $client->connect($set, function ($client, $result) {
            if (!$result) {
                throw new SwooleException($client->connect_error);
            } else {
                $client->isAffair = false;
                $client->client_id = $this->mysql_max_count;
                $this->mysql_max_count++;
                $this->pushToPool($client);
            }
        });
    }

    /**
     * 准备一个mysql
     */
    public function prepareOne()
    {
        if ($this->prepareLock) return;
        if ($this->mysql_max_count >= $this->config->get('database.asyn_max_count', 10)) {
            return;
        }
        $this->prepareLock = true;
        $this->reconnect();
    }

    /**
     * @return string
     */
    public function getAsynName()
    {
        return self::AsynName;
    }

    /**
     * @return int
     */
    public function getMessageType()
    {
        return SwooleMarco::MSG_TYPE_MYSQL_MESSAGE;
    }

    /**
     * 获取绑定值
     */
    public function bind($object)
    {
        return spl_object_hash($object);
    }

    /**
     * 释放绑定
     * @param $bind_id
     */
    public function free_bind($bind_id)
    {
        $client = $this->bind_pool[$bind_id];
        unset($this->bind_pool[$bind_id]);
        if ($client != null) {
            $this->pushToPool($client);
        }
    }

    /**
     * 开启一个事务
     * @param $object
     * @return string
     * @throws SwooleException
     */
    public function begin($object)
    {
        $id = $this->bind($object);
        $this->query(null, $id, 'begin');
        return $id;
    }

    /**
     * 提交一个事务
     * @param $object
     * @throws SwooleException
     */
    public function commit($object)
    {
        $id = $this->bind($object);
        $this->query(null, $id, 'commit');

    }

    /**
     * 回滚
     * @param $object
     * @throws SwooleException
     */
    public function rollback($object)
    {
        $id = $this->bind($object);
        $this->query(null, $id, 'rollback');
    }
}