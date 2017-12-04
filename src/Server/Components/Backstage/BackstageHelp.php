<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-11-7
 * Time: 下午3:18
 */

namespace Server\Components\Backstage;


use Server\CoreBase\PortManager;

class BackstageHelp
{
    public static $set = false;

    public static function init()
    {
        if (self::$set) {
            return;
        }
        if (!get_instance()->config->get('backstage.enable', false)) {
            return;
        }
        $name = get_instance()->config->get('backstage.socket');
        $port = get_instance()->config->get('backstage.websocket_port');
        $ports = get_instance()->config["ports"];
        $ports[] = [
            'socket_type' => PortManager::SOCK_WS,
            'socket_name' => $name,
            'socket_port' => $port,
            'route_tool' => 'ConsoleRoute',
            'pack_tool' => 'ConsolePack',
            'opcode' => PortManager::WEBSOCKET_OPCODE_TEXT,
            'event_controller_name' => Console::class,
            'connect_method_name' => "onConnect",
            'close_method_name' => "onClose",
            'method_prefix' => 'back_',
            'middlewares' => ['MonitorMiddleware', 'NormalHttpMiddleware']
        ];
        get_instance()->config->set("ports", $ports);
        $timerTask = get_instance()->config["timerTask"];
        $timerTask[] = [
            'model_name' => ConsoleModel::class,
            'method_name' => 'getNodeStatus',
            'interval_time' => '1',
        ];
        get_instance()->config->set("timerTask", $timerTask);
        self::$set = true;
    }
}