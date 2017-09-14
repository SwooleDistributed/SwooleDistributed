<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-14
 * Time: 下午1:58
 */

use Server\CoreBase\PortManager;

$config['ports'][] = [
    'socket_type' => PortManager::SOCK_HTTP,
    'socket_name' => '0.0.0.0',
    'socket_port' => 8081,
    'route_tool' => 'NormalRoute'
];

$config['ports'][] = [
    'socket_type' => PortManager::SOCK_WS,
    'socket_name' => '0.0.0.0',
    'socket_port' => 8082,
    'route_tool' => 'NormalRoute',
    'pack_tool' => 'NonJsonPack',
    'opcode' => PortManager::WEBSOCKET_OPCODE_TEXT
];

$config['ports'][] = [
    'socket_type' => PortManager::SOCK_TCP,
    'socket_name' => '0.0.0.0',
    'socket_port' => 8083,
    'pack_tool' => 'LenJsonPack',
    'route_tool' => 'NormalRoute',
];

return $config;