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
    const SWOOLE_SSL = SWOOLE_SSL;
    const SOCK_HTTP = 10;
    const SOCK_WS = 11;
    const WEBSOCKET_OPCODE_TEXT = WEBSOCKET_OPCODE_TEXT;
    const WEBSOCKET_OPCODE_BINARY = WEBSOCKET_OPCODE_BINARY;

    protected $packs = [];
    protected $routes = [];
    protected $middlewares = [];
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
            //获得set
            $set = $this->getProbufSet($value['socket_port']);
            if (array_key_exists('ssl_cert_file', $value)) {
                $set['ssl_cert_file'] = $value['ssl_cert_file'];
            }
            if (array_key_exists('ssl_key_file', $value)) {
                $set['ssl_key_file'] = $value['ssl_key_file'];
            }
            $socket_ssl = $value['socket_ssl'] ?? false;
            if ($value['socket_type'] == self::SOCK_HTTP || $value['socket_type'] == self::SOCK_WS) {
                if ($socket_ssl) {
                    $port = $swoole_server->server->listen($value['socket_name'], $value['socket_port'], self::SOCK_TCP | self::SWOOLE_SSL);
                } else {
                    $port = $swoole_server->server->listen($value['socket_name'], $value['socket_port'], self::SOCK_TCP);
                }
                if ($port == false) {
                    throw new \Exception("{$value['socket_port']}端口创建失败");
                }
                if ($value['socket_type'] == self::SOCK_HTTP) {
                    $set['open_http_protocol'] = true;
                    $port->set($set);
                    $port->on('request', [$swoole_server, $value['request'] ?? 'onSwooleRequest']);
                    $port->on('handshake', function () {
                        return false;
                    });
                } else {
                    $set['open_http_protocol'] = true;
                    $set['open_websocket_protocol'] = true;
                    $port->set($set);
                    $port->on('open', [$swoole_server, $value['open'] ?? 'onSwooleWSOpen']);
                    $port->on('message', [$swoole_server, $value['message'] ?? 'onSwooleWSMessage']);
                    $port->on('close', [$swoole_server, $value['close'] ?? 'onSwooleWSClose']);
                    $port->on('handshake', [$swoole_server, $value['handshake'] ?? 'onSwooleWSHandShake']);
                }
            } else {
                if ($socket_ssl) {
                    $port = $swoole_server->server->listen($value['socket_name'], $value['socket_port'], $value['socket_type'] | self::SWOOLE_SSL);
                } else {
                    $port = $swoole_server->server->listen($value['socket_name'], $value['socket_port'], $value['socket_type']);
                }
                if ($port == false) {
                    throw new \Exception("{$value['socket_port']}端口创建失败");
                }
                $port->set($set);
                $port->on('connect', [$swoole_server, $value['connect'] ?? 'onSwooleConnect']);
                $port->on('receive', [$swoole_server, $value['receive'] ?? 'onSwooleReceive']);
                $port->on('close', [$swoole_server, $value['close'] ?? 'onSwooleClose']);
                $port->on('packet', [$swoole_server, $value['packet'] ?? 'onSwoolePacket']);
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
        foreach ($value['middlewares'] ?? [] as $middleware) {
            $this->middlewares[$value['socket_port']][] = $this->createMiddleware($middleware);
        }
    }

    /**
     * @param $pack_tool
     * @return mixed
     * @throws SwooleException
     */
    public static function createPack($pack_tool)
    {
        if (class_exists($pack_tool)) {
            $pack = new $pack_tool;
            return $pack;
        }
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
        if (class_exists($route_tool)) {
            $route = new $route_tool;
            return $route;
        }
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
     * @param $middleware_name
     * @return mixed
     * @throws SwooleException
     */
    protected function createMiddleware($middleware_name)
    {
        if (class_exists($middleware_name)) {
            return $middleware_name;
        }
        $middleware_class_name = "app\\Middlewares\\" . $middleware_name;
        if (class_exists($middleware_class_name)) {
            return $middleware_class_name;
        } else {
            $middleware_class_name = "Server\\Middlewares\\" . $middleware_name;
            if (class_exists($middleware_class_name)) {
                return $middleware_class_name;
            } else {
                throw new SwooleException("class $middleware_name is not exist.");
            }
        }
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
     * @param $fd
     * @return IPack
     */
    public function getPackFromFd($fd)
    {
        $port = get_instance()->getServerPort($fd);
        return $this->getPack($port);
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
     * @return null
     */
    public function getMiddlewares($port)
    {
        return $this->middlewares[$port] ?? [];
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

    /**
     * @param $server_port
     * @return string
     */
    public function getMethodPrefix($server_port)
    {
        $config = $this->portConfig[$server_port] ?? null;
        if ($config == null) return '';
        $method_name = $config['method_prefix'] ?? '';
        return $method_name;
    }

    /**
     * @param $fd
     * @throws \Throwable
     */
    public function eventClose($fd)
    {
        $server_port = get_instance()->getServerPort($fd);
        $uid = get_instance()->getUidFromFd($fd);
        try {
            $type = $this->getPortType($server_port);
        } catch (\Exception $e) {
            return;
        }
        if ($type == self::SOCK_HTTP) {
            return;
        }
        $config = $this->portConfig[$server_port] ?? null;
        if ($config == null) return;
        $controller_name = $config['event_controller_name'] ?? get_instance()->getEventControllerName();
        $method_name = ($config['method_prefix'] ?? '') . ($config['close_method_name'] ?? get_instance()->getCloseMethodName());
        $controller_instance = ControllerFactory::getInstance()->getController($controller_name);
        $context = [];
        $controller_instance->setContext($context);
        $controller_instance->setClientData($uid, $fd, null, $controller_name, $method_name, null);
        unset($context);
    }

    /**
     * @param $fd
     * @param null $request
     * @throws \Throwable
     */
    public function eventConnect($fd, $request = null)
    {
        $server_port = get_instance()->getServerPort($fd);
        $config = $this->portConfig[$server_port] ?? null;
        if ($config == null) return;
        $controller_name = $config['event_controller_name'] ?? get_instance()->getEventControllerName();
        $method_name = ($config['method_prefix'] ?? '') . ($config['connect_method_name'] ?? get_instance()->getConnectMethodName());
        $controller_instance = ControllerFactory::getInstance()->getController($controller_name);
        if ($request != null) {
            $controller_instance->setRequest($request);
        }
        $controller_instance->setClientData(null, $fd, null, $controller_name, $method_name, null);
    }

    /**
     * 获取端口状态
     * @return array
     */
    public function getPortStatus()
    {
        $status = $this->portConfig;
        foreach ($status as &$value) {
            $value['fdcount'] = 0;
        }
        foreach (get_instance()->server->connections as $fd) {
            $port = get_instance()->getServerPort($fd);
            if (array_key_exists($port, $status)) {
                $status[$port]['fdcount']++;
            }
        }
        return $status;
    }
}