<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-7-26
 * Time: 下午2:30
 */

namespace Server\CoreBase;

use Server\Pack\IPack;
use Server\Route\IRoute;
use Server\SwooleServer;

/**
 * 端口管理
 * Class PortManager
 * @package Server\CoreBase
 */
class PortManager
{
    const SOCK_TCP = SWOOLE_SOCK_TCP;
    const SOCK_UDP = SWOOLE_SOCK_UDP;
    const SOCK_TCP6 = SWOOLE_SOCK_TCP6;
    const SOCK_UDP6 = SWOOLE_SOCK_UDP6;
    const UNIX_DGRAM = SWOOLE_UNIX_DGRAM;
    const UNIX_STREAM = SWOOLE_UNIX_STREAM;
    const SOCK_HTTP = 10;
    const SOCK_WS = 11;
    const WEBSOCKET_OPCODE_TEXT = WEBSOCKET_OPCODE_TEXT;
    const WEBSOCKET_OPCODE_BINARY = WEBSOCKET_OPCODE_BINARY;

    protected $packs = [];
    protected $routes = [];
    protected $portConfig;
    public $websocket_enable = false;
    public $http_enable = false;
    public $tcp_enable = false;

    public function __construct($portConfig)
    {
        foreach ($portConfig as $key => $value) {
            $this->portConfig[$value['socket_port']] = $value;
            if ($value['socket_type'] == self::SOCK_WS) {
                $this->websocket_enable = true;
            } else if ($value['socket_type'] == self::SOCK_HTTP) {
                $this->http_enable = true;
            } else {
                $this->tcp_enable = true;
            }
            $this->addPort($value);
        }
    }

    /**
     * @return mixed
     * @internal param $type
     */
    public function getFirstTypePort()
    {
        if ($this->websocket_enable) {
            $type = self::SOCK_WS;
        } else if ($this->http_enable) {
            $type = self::SOCK_HTTP;
        } else {
            $type = self::SOCK_TCP;
        }
        foreach ($this->portConfig as $key => $value) {
            if ($value['socket_type'] == $type) {
                return $value;
            }
        }
        return $this->portConfig[0];
    }

    /**
     * 构架端口
     * @param SwooleServer $swoole_server
     * @param $first_port
     * @throws \Exception
     */
    public function buildPort(SwooleServer $swoole_server, $first_port)
    {
        foreach ($this->portConfig as $key => $value) {
            if ($value['socket_port'] == $first_port) continue;
            if ($value['socket_type'] == self::SOCK_HTTP || $value['socket_type'] == self::SOCK_WS) {
                $port = $swoole_server->server->listen($value['socket_name'], $value['socket_port'], SWOOLE_SOCK_TCP);
                if ($port == false) {
                    throw new \Exception("{$value['socket_port']}端口创建失败");
                }
                if ($value['socket_type'] == self::SOCK_HTTP) {
                    $port->on('request', [$swoole_server, 'onSwooleRequest']);
                    $port->on('handshake', function () {
                        return false;
                    });
                } else {
                    $port->on('open', [$swoole_server, 'onSwooleWSOpen']);
                    $port->on('message', [$swoole_server, 'onSwooleWSMessage']);
                    $port->on('close', [$swoole_server, 'onSwooleWSClose']);
                    $port->on('handshake', [$swoole_server, 'onSwooleWSHandShake']);
                }
            } else {
                $port = $swoole_server->server->listen($value['socket_name'], $value['socket_port'], $value['socket_type']);
                $port->set($this->getProbufSet($value['socket_port']));
                $port->on('connect', [$swoole_server, 'onSwooleConnect']);
                $port->on('receive', [$swoole_server, 'onSwooleReceive']);
                $port->on('close', [$swoole_server, 'onSwooleClose']);
                $port->on('packet', [$swoole_server, 'onSwoolePacket']);
            }

        }
    }

    /**
     * @param $value
     * @throws SwooleException
     */
    public function addPort($value)
    {
        if ($value['socket_type'] != self::SOCK_HTTP) {
            $this->packs[$value['socket_port']] = self::createPack($value['pack_tool']);
        }
        $this->routes[$value['socket_port']] = self::createRoute($value['route_tool']);
    }

    /**
     * @param $pack_tool
     * @return mixed
     * @throws SwooleException
     */
    public static function createPack($pack_tool)
    {
        //pack class
        $pack_class_name = "app\\Pack\\" . $pack_tool;
        if (class_exists($pack_class_name)) {
            $pack = new $pack_class_name;
        } else {
            $pack_class_name = "Server\\Pack\\" . $pack_tool;
            if (class_exists($pack_class_name)) {
                $pack = new $pack_class_name;
            } else {
                throw new SwooleException("class $pack_tool is not exist.");
            }
        }
        return $pack;
    }

    /**
     * @param $route_tool
     * @return mixed
     * @throws SwooleException
     */
    public static function createRoute($route_tool)
    {
        $route_class_name = "app\\Route\\" . $route_tool;
        if (class_exists($route_class_name)) {
            $route = new $route_class_name;
        } else {
            $route_class_name = "Server\\Route\\" . $route_tool;
            if (class_exists($route_class_name)) {
                $route = new $route_class_name;
            } else {
                throw new SwooleException("class $route_tool is not exist.");
            }
        }
        return $route;
    }

    /**
     * @param $port
     * @return IPack
     */
    public function getPack($port)
    {
        return $this->packs[$port] ?? null;
    }

    /**
     * @param $port
     * @return IRoute
     */
    public function getRoute($port)
    {
        return $this->routes[$port] ?? null;
    }

    /**
     * @param $port
     * @return mixed
     */
    public function getProbufSet($port)
    {
        if ($this->getPack($port) == null) {
            return [];
        }
        return $this->getPack($port)->getProbufSet();
    }

    public function getOpCode($port)
    {
        return $this->portConfig[$port]['opcode'] ?? WEBSOCKET_OPCODE_TEXT;
    }

    public static function getTypeName($type)
    {
        switch ($type) {
            case SWOOLE_SOCK_TCP:
                return 'TCP';
            case SWOOLE_SOCK_UDP:
                return 'UDP';
            case SWOOLE_SOCK_TCP6:
                return 'TCP6';
            case SWOOLE_SOCK_UDP6:
                return 'UDP6';
            case SWOOLE_UNIX_DGRAM:
                return 'UNIX_DGRAM';
            case SWOOLE_UNIX_STREAM:
                return 'UNIX_STREAM';
            case self::SOCK_HTTP:
                return 'HTTP';
            case self::SOCK_WS:
                return 'WebSocket';
            default:
                return 'TCP';
        }
    }


    public function getPortType($port)
    {
        if (!array_key_exists($port, $this->portConfig)) {
            throw new SwooleException('port 不存在');
        }
        $config = $this->portConfig[$port];
        return $config['socket_type'];
    }
}