<?php
/**
 * mysql 异步客户端连接池
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-22
 * Time: 上午10:19
 */

namespace Server\Asyn\Mysql;

use Server\Asyn\AsynPool;
use Server\CoreBase\SwooleException;
use Server\Memory\Pool;

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
    private $active;
    private $mysql_client;

    public function __construct($config, $active)
    {
        parent::__construct($config);
        $this->active = $active;
        $this->bind_pool = [];
        $this->client_max_count = $this->config->get('mysql.asyn_max_count', 10);
    }

    /**
     * @return Miner
     */
    public function installDbBuilder()
    {
        $this->dbQueryBuilder = Pool::getInstance()->get(Miner::class)->setPool($this);
        return $this->dbQueryBuilder;
    }

    /**
     * 断开链接
     * @param $client
     */
    public function onClose($client)
    {
        $client->isClose = true;
    }

    /**
     * @return string
     */
    public function getAsynName()
    {
        return self::AsynName . ":" . $this->active;
    }

    /**
     * 开启一个事务
     * @param $object
     * @param $callback
     * @return string
     */
    public function begin($object, $callback)
    {
        $id = $this->bind($object);
        $this->query($callback, $id, 'begin');
        return $id;
    }

    /**
     * 获取绑定值
     */
    public function bind($object)
    {
        if (!isset($object->UBID)) {
            $object->UBID = 0;
        }
        $object->UBID++;
        return spl_object_hash($object) . $object->UBID;
    }

    /**
     * 执行一个sql语句
     * @param $callback
     * @param null $bind_id
     * @param null $sql
     * @return int
     * @throws SwooleException
     * @throws \Exception
     */
    public function query($callback, $bind_id = null, $sql = null)
    {
        if ($this->dbQueryBuilder == null) {
            throw new \Exception('你需要先调用installDbBuilder');
        }
        if ($sql == null) {
            $sql = $this->dbQueryBuilder->getStatement(false);
            $this->dbQueryBuilder->clear();
        }
        if (empty($sql)) {
            throw new SwooleException('sql empty');
        }
        $data = [
            'sql' => $sql
        ];
        $data['token'] = $this->addTokenCallback($callback);
        if ($bind_id != null) {
            $data['bind_id'] = $bind_id;
        }
        $this->execute($data);
        return $data['token'];
    }

    /**
     * 执行mysql命令
     * @param $data
     * @throws SwooleException
     */
    public function execute($data)
    {
        $client = null;
        $bind_id = $data['bind_id']??null;
        if ($bind_id != null) {//绑定
            $client = $this->bind_pool[$bind_id]['client']??null;
            $sql = strtolower($data['sql']);
            if ($sql != 'begin' && $client == null) {
                throw new SwooleException('error mysql affairs not begin.');
            }
        }
        if ($client == null) {
            $client = $this->shiftFromPool($data);
            if ($client) {
                if ($client->isClose??false) {
                    $this->reconnect($client);
                    $this->commands->unshift($data);
                    return;
                }
                if ($bind_id != null) {//添加绑定
                    $this->bind_pool[$bind_id]['client'] = $client;
                }
            } else {
                return;
            }
        }

        $sql = $data['sql'];
        $client->query($sql, function ($client, $result) use ($data) {
            if ($result === false) {
                if ($client->errno == 2006 || $client->errno == 2013) {//断线重连
                    $client->close();
                    $this->reconnect($client);
                    if (!isset($data['bind_id'])) {//非事务可以重新执行
                        $this->commands->unshift($data);
                    }
                    return;
                } else {//发生错误
                    if (isset($data['bind_id'])) {//事务的话要rollback
                        $data['sql'] = 'rollback';
                        $this->commands->unshift($data);
                    }
                    //设置错误信息
                    $data['result']['error'] = "[mysql]:" . $client->error . "[sql]:" . $data['sql'];
                }
            }
            $sql = strtolower($data['sql']);
            if ($sql == 'begin') {
                $data['result'] = $data['bind_id'];
            } else {
                $data['result']['result'] = $result;
                $data['result']['affected_rows'] = $client->affected_rows;
                $data['result']['insert_id'] = $client->insert_id;
            }
            //分发消息
            $this->distribute($data);
            //不是绑定的连接就回归连接
            if (!isset($data['bind_id'])) {
                $this->pushToPool($client);
            } else {//事务
                $bind_id = $data['bind_id'];
                if ($sql == 'commit' || $sql == 'rollback') {//结束事务
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
        $set = $this->config['mysql'][$this->active];
        $client->on('Close', [$this, 'onClose']);
        $client->connect($set, function ($client, $result) {
            if (!$result) {
                throw new SwooleException($client->connect_error);
            } else {
                $client->isClose = false;
                $this->pushToPool($client);
            }
        });
    }

    /**
     * 释放绑定
     * @param $bind_id
     */
    public function free_bind($bind_id)
    {
        $client = $this->bind_pool[$bind_id]['client'];
        if ($client != null) {
            $this->pushToPool($client);
        }
        unset($this->bind_pool[$bind_id]);
    }

    /**
     * 准备一个mysql
     */
    public function prepareOne()
    {
        if (parent::prepareOne()) {
            $this->reconnect();
        }
    }

    /**
     * 开启一个协程事务
     * @param $object
     * @return MySqlCoroutine
     * @throws \Exception
     */
    public function coroutineBegin($object)
    {
        if ($this->dbQueryBuilder == null) {
            throw new \Exception('你需要先调用installDbBuilder');
        }
        $id = $this->bind($object);
        return $this->dbQueryBuilder->coroutineSend($id, 'begin');
    }

    /**
     * 提交一个事务
     * @param $callback
     * @param $id
     */
    public function commit($callback, $id)
    {
        $this->query($callback, $id, 'commit');

    }

    /**
     * 协程Commit
     * @param $id
     * @return MySqlCoroutine
     * @throws \Exception
     */
    public function coroutineCommit($id)
    {
        if ($this->dbQueryBuilder == null) {
            throw new \Exception('你需要先调用installDbBuilder');
        }
        return $this->dbQueryBuilder->coroutineSend($id, 'commit');
    }

    /**
     * 回滚
     * @param $callback
     * @param $id
     */
    public function rollback($callback, $id)
    {
        $this->query($callback, $id, 'rollback');
    }

    /**
     * 协程Rollback
     * @param $id
     * @return MySqlCoroutine
     * @throws \Exception
     */
    public function coroutineRollback($id)
    {
        if ($this->dbQueryBuilder == null) {
            throw new \Exception('你需要先调用installDbBuilder');
        }
        return $this->dbQueryBuilder->coroutineSend($id, 'rollback');
    }

    /**
     * 获取同步
     * @return Miner
     */
    public function getSync()
    {
        if ($this->mysql_client!=null) return $this->mysql_client;
        $activeConfig = $this->config['mysql'][$this->active];
        $this->mysql_client = new Miner();
        $this->mysql_client->pdoConnect($activeConfig);
        return $this->mysql_client;
    }

    /**
     * 销毁Client
     * @param $client
     */
    protected function destoryClient($client)
    {
        $client->close();
    }
}