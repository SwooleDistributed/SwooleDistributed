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
     * @var AMQPChannel
     */
    protected $channel;

    public function start($process)
    {
        $this->initAsynPools();
        $this->connectAMQP();
    }

    /**
     * @param null $active
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
        $connection = new AMQP($host, $port, $user, $password, $vhost);
        $this->channel = $connection->channel();
    }

    /**
     * 创建完全匹配队列名称的消费者
     * @param $queue
     * @param int $prefetch_count
     * @param bool $global
     * @param $exchange
     * @param $consumerTag
     */
    protected function createDirectConsume($queue, $prefetch_count = 2, $global = false, $exchange = null, $consumerTag = null)
    {
        if ($exchange == null) {
            $exchange = create_uuid('route');
        }
        if ($consumerTag == null) {
            $consumerTag = create_uuid('consumer');
        }
        $this->channel->queue_declare($queue, true);
        $this->channel->exchange_declare($exchange, 'direct');
        $this->channel->queue_bind($queue, $exchange);
        $this->channel->basic_qos(0, $prefetch_count, $global);
        $this->channel->basic_consume($queue, $consumerTag, false, false, false, false, [$this, 'process_message']);
    }

    /**
     * 初始化各种连接池
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
    public function process_message(AMQPMessage $message)
    {
        go(function () use ($message) {
            $task = Pool::getInstance()->get($this->route($message->getBody()));
            $task->reUse();
            $task->initialization($message);
            $task->handle($message->getBody());
        });
    }

    /**
     * 路由消息返回class名称
     * @param $body
     * @return string
     */
    protected abstract function route($body);

}