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
use Server\Coroutine\Coroutine;

class ConsulHelp
{
    protected static $is_leader = null;
    protected static $session_id;
    protected static $table;
    const DISPATCH_KEY = 'consul_service';
    const LEADER_KEY = 'consul_leader';

    /**
     * reload时获取一下
     */
    public static function start()
    {
        if (get_instance()->config->get('consul.enable', false)) {
            //提取SDHelpProcess中的services
            Coroutine::startCoroutine(function () {
                $result = yield ProcessManager::getInstance()
                    ->getRpcCall(SDHelpProcess::class)->getData(ConsulHelp::DISPATCH_KEY);
                ConsulHelp::getMessgae($result);
            });
            //提取SDHelpProcess中的leader
            Coroutine::startCoroutine(function () {
                $result = yield ProcessManager::getInstance()
                    ->getRpcCall(SDHelpProcess::class)->getData(ConsulHelp::LEADER_KEY);
                ConsulHelp::leaderChange($result);
            });
            //监听服务改变
            EventDispatcher::getInstance()->add(ConsulHelp::DISPATCH_KEY, function (Event $event) {
                ConsulHelp::getMessgae($event->data);
            });
            //监听leader改变
            EventDispatcher::getInstance()->add(ConsulHelp::LEADER_KEY, function (Event $event) {
                ConsulHelp::leaderChange($event->data);
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


    /**
     * leader变更
     * @param $is_leader
     */
    public static function leaderChange($is_leader)
    {
        if (get_instance()->server->worker_id == 0) {
            if ($is_leader !== self::$is_leader) {
                if ($is_leader) {
                    print_r("Leader变更，被选举为Leader\n");
                } else {
                    print_r("Leader变更，本机不是Leader\n");
                }
            }
        }
        self::$is_leader = $is_leader;
    }

    /**
     * 是否是leader
     * @return bool
     */
    public static function isLeader()
    {
        if (self::$is_leader == null) {
            return false;
        }
        return self::$is_leader;
    }


}