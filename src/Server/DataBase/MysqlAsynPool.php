<?php
/**
 * mysql 异步客户端连接池
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-22
 * Time: 上午10:19
 */

namespace Server\DataBase;


use Noodlehaus\Config;
use Server\CoreBase\SwooleException;
use Server\SwooleMarco;
use Server\SwooleServer;

class MysqlAsynPool extends AsynPool
{
    const AsynName = 'mysql';
    /**
     * @var Miner
     */
    public $dbQueryBuilder;
    protected $mysql_max_count = 0;

    /**
     * 作为客户端的初始化
     * @param $worker_id
     */
    public function worker_init($worker_id)
    {
        parent::worker_init($worker_id);
        $this->dbQueryBuilder = new Miner();
    }

    /**
     * 执行一个sql语句
     * @param $callback
     * @param $sql
     */
    public function query($callback,$sql=null)
    {
        if($sql==null){
            $sql = $this->dbQueryBuilder->getStatement(false);
        }
        if (empty($sql)){
            throw new SwooleException('sql empty');
        }
        $data = [
            'sql' => $sql
        ];
        $data['token'] = $this->addTokenCallback($callback);
        //写入管道
        $this->asyn_manager->writePipe($this, $data,$this->worker_id);
    }

    /**
     * 执行mysql命令
     * @param $data
     */
    public function execute($data)
    {
        if (count($this->pool)==0) {//代表目前没有可用的连接
            $this->prepareOne();
            $this->commands->push($data);
        } else {
            $client = $this->pool->shift();
            $sql = $data['sql'];
            $client->query($sql, function ($client, $result) use ($data) {
                if ($result === false)
                {
                    if($client->errno==2006||$client->errno==2013){//断线重连
                        $this->reconnect($client);
                        $this->commands->unshift($data);
                    }else {
                        throw new SwooleException("[mysql]:".$client->error);
                    }
                }
                $data['result'] = $result;
                unset($data['sql']);
                //给worker发消息
                $this->asyn_manager->sendMessageToWorker($this, $data);
                //回归连接
                $this->pushToPool($client);
            });
        }
    }

    /**
     * 重连或者连接
     * @param null $client
     */
    public function reconnect($client=null){
        if($client==null) {
            $client = new \swoole_mysql();
        }
        $set = $this->config['database'][$this->config['database']['active']];
        $client->connect($set, function ($client, $result) {
            if (!$result) {
                throw new SwooleException($client->connect_error);
            }else{
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
        if ($this->mysql_max_count > $this->config->get('database.asyn_max_count', 10)) {
            return;
        }
        $this->reconnect();
    }

    /**
     * @return string
     */
    public function getAsynName(){
        return self::AsynName;
    }
    /**
     * @return int
     */
    public function getMessageType(){
        return SwooleMarco::MSG_TYPE_MYSQL_MESSAGE;
    }
}