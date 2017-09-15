<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-9-7
 * Time: 上午10:35
 */

namespace app\AMQPTasks;

use PhpAmqpLib\Message\AMQPMessage;
use Server\Components\AMQPTaskSystem\AMQPTask;
use Server\Models\TestModel;

class TestAMQPTask extends AMQPTask
{
    /**
     * @var TestModel
     */
    public $TestModel;

    public function initialization(AMQPMessage $message)
    {
        parent::initialization($message);
        $this->TestModel = $this->loader->model(TestModel::class, $this);
    }

    /**
     * handle
     * @param $body
     */
    public function handle($body)
    {
        var_dump($body);
        $this->ack();
    }
}