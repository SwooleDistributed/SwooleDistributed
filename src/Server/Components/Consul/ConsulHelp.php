<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-9
 * Time: 下午12:10
 */

namespace Server\Components\Consul;

use Server\Components\Event\Event;
use Server\Components\Event\EventDispatcher;
use Server\Components\Process\ProcessManager;
use Server\Components\SDHelp\SDHelpProcess;

class ConsulHelp
{
    protected static $is_leader = null;
    protected static $session_id;
    protected static $table;
    const DISPATCH_KEY = 'consul_service';
    const HEALTH = '_consul_health';

    /**
     * reload时获取一下
     * @throws \Exception
     */
    public static function start()
    {
        if (get_instance()->config->get('consul.enable', false)) {
            //提取SDHelpProcess中的services
            $result = ProcessManager::getInstance()->getRpcCall(SDHelpProcess::class)->getData(ConsulHelp::DISPATCH_KEY);
            ConsulHelp::getMessgae($result);
            //监听服务改变
            EventDispatcher::getInstance()->add(ConsulHelp::DISPATCH_KEY, function (Event $event) {
                ConsulHelp::getMessgae($event->data);
            });
        }
    }

    /**
     * @param $message
     */
    public static function getMessgae($message)
    {
        if (empty($message)) return;
        foreach ($message as $key => $value) {
            ConsulServices::getInstance()->updateServies($key, $value);
        }
    }
}