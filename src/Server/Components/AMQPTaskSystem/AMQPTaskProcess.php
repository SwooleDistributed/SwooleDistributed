<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-15
 * Time: 上午10:52
 */

namespace Server\Components\AMQPTaskSystem;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Server\Asyn\AMQP\AMQP;
use Server\Asyn\Mysql\MysqlAsynPool;
use Server\Asyn\Redis\RedisAsynPool;
use Server\Components\Process\Process;
use Server\Memory\Pool;

abstract class AMQPTaskProcess extends Process
{
    /**
     * @var AMQP
     */
    protected $connection;

    /**
     * @param $process
     * @throws \Exception
     */
    public function start($process)
    {
        $this->initAsynPools();
        $this->connectAMQP();
    }

    /**
     * @param null $active
     * @throws \Exception
     */
    protected function connectAMQP($active = null)
    {
        if (!$this->config->has('amqp')) {
            secho("AMQP", "未发现AMQP配置文件");
            while (true) {

            }
            return;
        }
        if (empty($active)) {
            $active = $this->config['amqp']['active'];
        }
        $host = $this->config['amqp'][$active]['host'];
        $port = $this->config['amqp'][$active]['port'];
        $user = $this->config['amqp'][$active]['user'];
        $password = $this->config['amqp'][$active]['password'];
        $vhost = $this->config['amqp'][$active]['vhost'];
        $this->connection = new AMQP($host, $port, $user, $password, $vhost);
    }

    /**
     * 创建完全匹配队列名称的消费者
     * @param $channel
     * @param $queue
     * @param int $prefetch_count
     * @param bool $global
     * @param $exchange
     * @param $consumerTag
     */
    protected function createDirectConsume(AMQPChannel $channel, $queue, $prefetch_count = 2, $global = false, $exchange = null, $consumerTag = null)
    {
        if ($exchange == null) {
            $exchange = create_uuid('route');
        }
        if ($consumerTag == null) {
            $consumerTag = create_uuid('consumer');
        }
        $channel->queue_declare($queue, true);
        $channel->exchange_declare($exchange, 'direct');
        $channel->queue_bind($queue, $exchange);
        $channel->basic_qos(0, $prefetch_count, $global);
        $channel->basic_consume($queue, $consumerTag, false, false, false, false, [$this, '_process_message']);
    }

    /**
     * 初始化各种连接池
     * @throws \Server\CoreBase\SwooleException
     */
    protected function initAsynPools()
    {
        if ($this->config->get('redis.enable', true)) {
            get_instance()->addAsynPool('redisPool', new RedisAsynPool($this->config, $this->config->get('redis.active')));
        }
        if ($this->config->get('mysql.enable', true)) {
            get_instance()->addAsynPool('mysqlPool', new MysqlAsynPool($this->config, $this->config->get('mysql.active')));
        }
    }

    /**
     * 处理消息
     * @param $message
     */
    public function _process_message(AMQPMessage $message)
    {
        go(function ()use ($message){
            $this->process_message($message);
        });
    }

    public function process_message(AMQPMessage $message)
    {
        $task = Pool::getInstance()->get($this->route($message->getBody()));
        $task->reUse();
        $task->initialization($message);
        $task->handle($message->getBody());
    }
    /**
     * 成功应答
     * @param $message
     */
    public function ack($message)
    {
        $channel = $message->delivery_info['channel'];
        $delivery_tag = $message->delivery_info['delivery_tag'];
        $channel->basic_ack($delivery_tag);
    }

    /**
     * 拒绝（是否重新如队列）
     * @param $message
     * @param bool $requeue
     */
    public function reject($message, $requeue = true)
    {
        $channel = $message->delivery_info['channel'];
        $delivery_tag = $message->delivery_info['delivery_tag'];
        $channel->basic_reject($delivery_tag, $requeue);
    }
    /**
     * 路由消息返回class名称
     * @param $body
     * @return string
     */
    protected function route($body){

    }

}