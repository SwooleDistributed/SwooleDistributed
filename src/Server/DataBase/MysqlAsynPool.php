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
     * 执行mysql命令
     * @param $data
     * @param bool $isBind 是否是bind命令队列的调用
     */
    public function execute($data, $isBind = false)
    {
        $client = null;
        $bind_id = $data['bind_id']??null;
        if ($bind_id != null) {//绑定
            $client = $this->bind_pool[$bind_id]['client']??null;
            if (!$isBind && strtolower($data['sql']) != 'begin') {//如果不是begin全部扔到队列中，begin获取客户端链接
                $this->bind_pool[$bind_id]['affairs'][] = $data;
                return;
            }
        }
        if ($client == null) {
            if (count($this->pool) == 0) {//代表目前没有可用的连接
                $this->prepareOne();
                $this->commands->push($data);
                return;
            } else {
                $client = $this->pool->shift();
                if ($client->isClose??false) {
                    $this->reconnect($client);
                    $this->commands->push($data);
                    return;
                }
                if ($bind_id != null) {//添加绑定
                    $this->bind_pool[$bind_id]['client'] = $client;
                }
            }
        }

        $sql = $data['sql'];
        $client->query($sql, function ($client, $result) use ($data) {
            if ($result === false) {
                if ($client->errno == 2006 || $client->errno == 2013) {//断线重连
                    $this->reconnect($client);
                    if (!isset($data['bind_id'])) {//非事务可以重新执行
                        $this->commands->unshift($data);
                    } else {//事务重新开启一次
                        $data['sql'] = 'begin';
                        $this->commands->unshift($data);
                        $bind_id = $data['bind_id'];
                        $this->bind_pool[$bind_id]['affairs'][] = array_merge($this->bind_pool[$bind_id]['backup_affairs'], $this->bind_pool[$bind_id]['affairs']);
                        $this->bind_pool[$bind_id]['backup_affairs'] = [];
                    }
                    return;
                } else {
                    if (isset($data['bind_id'])) {//事务的话要rollback
                        $bind_id = $data['bind_id'];
                        $this->bind_pool[$bind_id]['affairs'] = ['rollback'];
                    }
                    //设置错误信息
                    $data['result']['error'] = "[mysql]:" . $client->error . "[sql]:" . $data['sql'];
                }
            }
            $data['result']['client_id'] = $client->client_id;
            $data['result']['result'] = $result;
            $data['result']['affected_rows'] = $client->affected_rows;
            $data['result']['insert_id'] = $client->insert_id;
            $sql = strtolower($data['sql']);
            //不是绑定的连接就回归连接
            if (!isset($data['bind_id'])) {
                $this->pushToPool($client);
            } else {//事务
                $bind_id = $data['bind_id'];
                $affair = array_shift($this->bind_pool[$bind_id]['affairs']);
                $this->bind_pool[$bind_id]['backup_affairs'] = $affair;
                if ($affair != null) {
                    $this->execute($affair, true);
                }
                if ($sql == 'commit' || $sql == 'rollback') {//结束事务
                    $this->free_bind($bind_id);
                }
            }

            //给worker发消息
            $this->asyn_manager->sendMessageToWorker($this, $data);
        });
    }

    /**
     * 准备一个mysql
     */
    public function prepareOne()
    {
        if ($this->mysql_max_count + $this->waitConnetNum >= $this->config->get('database.asyn_max_count', 10)) {
            return;
        }
        $this->reconnect();
    }

    /**
     * 重连或者连接
     * @param null $client
     */
    public function reconnect($client = null)
    {
        $this->waitConnetNum++;
        if ($client == null) {
            $client = new \swoole_mysql();
        }
        $set = $this->config['database'][$this->config['database']['active']];
        $client->connect($set, function ($client, $result) {
            $this->waitConnetNum--;
            if (!$result) {
                throw new SwooleException($client->connect_error);
            } else {
                $client->isClose = false;
                if (!isset($client->client_id)) {
                    $client->client_id = $this->mysql_max_count;
                    $this->mysql_max_count++;
                }
                $this->pushToPool($client);
            }
        });
        $client->on('Close',[$this,'onClose']);
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
     * @throws SwooleException
     */
    public function query($callback, $bind_id = null, $sql = null)
    {
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
        if (!empty($bind_id)) {
            $data['bind_id'] = $bind_id;
        }
        //写入管道
        $this->asyn_manager->writePipe($this, $data, $this->worker_id);
    }

    /**
     * 提交一个事务
     * @param $id
     */
    public function commit($id)
    {
        $this->query(null, $id, 'commit');

    }

    /**
     * 回滚
     * @param $id
     */
    public function rollback($id)
    {
        $this->query(null, $id, 'rollback');
    }
}