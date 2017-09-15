<?php

namespace Server\Components\AMQPTaskSystem;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Server\CoreBase\CoreBase;
use Server\Memory\Pool;

/**
 * Controller 控制器
 * 对象池模式，实例会被反复使用，成员变量缓存数据记得在销毁时清理
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 上午11:59
 */
abstract class AMQPTask extends CoreBase
{
    protected $message;
    protected $body;
    /**
     * @var AMQPChannel
     */
    protected $channel;
    protected $delivery_tag;

    /**
     * Controller constructor.
     */
    final public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $message AMQPMessage
     * @return \Generator
     */
    public function initialization(AMQPMessage $message)
    {
        $this->message = $message;
        $this->body = $message->body;
        $this->channel = $this->message->delivery_info['channel'];
        $this->delivery_tag = $this->message->delivery_info['delivery_tag'];
    }

    /**
     * handle
     * @param $body
     * @return
     */
    abstract protected function handle($body);

    /**
     * 成功应答
     */
    public function ack()
    {
        $this->channel->basic_ack($this->delivery_tag);
    }

    /**
     * 拒绝（是否重新如队列）
     * @param bool $requeue
     */
    public function reject($requeue = true)
    {
        $this->channel->basic_reject($this->delivery_tag, $requeue);
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        if ($this->is_destroy) {
            return;
        }
        parent::destroy();
        Pool::getInstance()->push($this);
        $this->message = null;
    }

}