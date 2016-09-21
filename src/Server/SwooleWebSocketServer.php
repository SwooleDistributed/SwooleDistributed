<?php
/**
 * 包含http服务器
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-29
 * Time: 上午9:42
 */

namespace Server;


use Server\CoreBase\ControllerFactory;
use Server\CoreBase\SwooleException;
use Server\Pack\IPack;

abstract class SwooleWebSocketServer extends SwooleHttpServer
{
    /**
     * websocket封包器
     * @var IPack
     */
    public $webSocketPack;
    public $opcode;
    public $webSocketEnable;

    public function __construct()
    {
        parent::__construct();
        $this->webSocketEnable = $this->config->get('websocket.enable', false);
        $this->opcode = $this->config->get('websocket.opcode', WEBSOCKET_OPCODE_TEXT);
        //pack class
        $pack_class_name = "\\app\\Pack\\" . $this->config['websocket']['pack_tool'];
        if (class_exists($pack_class_name)) {
            $this->webSocketPack = new $pack_class_name;
        } else {
            $pack_class_name = "\\Server\\Pack\\" . $this->config['websocket']['pack_tool'];
            if (class_exists($pack_class_name)) {
                $this->webSocketPack = new $pack_class_name;
            } else {
                throw new SwooleException("class {$this->config['websocket']['pack_tool']} is not exist.");
            }
        }
    }

    public function start()
    {
        if (!$this->webSocketEnable) {
            parent::start();
            return;
        }
        $dispatch_mode = $this->config['server']['set']['dispatch_mode'];
        if ($dispatch_mode != 2 && $dispatch_mode != 5) {
            print_r("启动失败，websocket模式下dispatch_mode只能是2或者5.\n");
            exit();
        }
        //开启一个websocket服务器
        $this->server = new \swoole_websocket_server($this->http_socket_name, $this->http_port);
        $this->server->on('Start', [$this, 'onSwooleStart']);
        $this->server->on('WorkerStart', [$this, 'onSwooleWorkerStart']);
        $this->server->on('WorkerStop', [$this, 'onSwooleWorkerStop']);
        $this->server->on('Task', [$this, 'onSwooleTask']);
        $this->server->on('Finish', [$this, 'onSwooleFinish']);
        $this->server->on('PipeMessage', [$this, 'onSwoolePipeMessage']);
        $this->server->on('WorkerError', [$this, 'onSwooleWorkerError']);
        $this->server->on('ManagerStart', [$this, 'onSwooleManagerStart']);
        $this->server->on('ManagerStop', [$this, 'onSwooleManagerStop']);
        $this->server->on('request', [$this, 'onSwooleRequest']);
        $this->server->on('open', [$this, 'onSwooleWSOpen']);
        $this->server->on('message', [$this, 'onSwooleWSMessage']);
        $this->server->on('close', [$this, 'onSwooleWSClose']);
        $set = $this->setServerSet();
        $set['daemonize'] = self::$daemonize ? 1 : 0;
        $this->server->set($set);
        $this->port = $this->server->listen($this->socket_name, $this->port, $this->socket_type);
        $this->port->set($set);
        $this->port->on('connect', [$this, 'onSwooleConnect']);
        $this->port->on('receive', [$this, 'onSwooleReceive']);
        $this->port->on('close', [$this, 'onSwooleClose']);
        $this->port->on('Packet', [$this, 'onSwoolePacket']);
        $this->beforeSwooleStart();
        $this->server->start();
    }

    /**
     * 判断是tcp还是websocket进行发送
     * @param $fd
     * @param $data
     */
    public function send($fd, $data)
    {
        if (!$this->webSocketEnable) {
            parent::send($fd, $data);
            return;
        }
        if ($this->isWebSocket($fd)) {
            $data = substr($data, 4);
            $this->server->push($fd, $data, $this->opcode);
        } else {
            $this->server->send($fd, $data);
        }
    }

    /**
     * websocket连接上时
     * @param $server
     * @param $request
     */
    public function onSwooleWSOpen($server, $request)
    {

    }

    /**
     * websocket收到消息时
     * @param $server
     * @param $frame
     */
    public function onSwooleWSMessage($server, $frame)
    {
        $this->onSwooleWSAllMessage($server, $frame->fd, $frame->data);
    }

    /**
     * wensocket合并后完整的消息
     * @param $server
     * @param $data
     */
    public function onSwooleWSAllMessage($serv, $fd, $data)
    {
        //反序列化，出现异常断开连接
        try {
            $client_data = $this->webSocketPack->unPack($data);
        } catch (\Exception $e) {
            $serv->close($fd);
            return;
        }
        //client_data进行处理
        $client_data = $this->route->handleClientData($client_data);
        $controller_name = $this->route->getControllerName();
        $controller_instance = ControllerFactory::getInstance()->getController($controller_name);
        if ($controller_instance != null) {
            $uid = $serv->connection_info($fd)['uid']??0;
            $controller_instance->setClientData($uid, $fd, $client_data);
            $method_name = $this->config->get('websocket.method_prefix', '') . $this->route->getMethodName();
            try {
                $generator = call_user_func([$controller_instance, $method_name]);
                if ($generator instanceof \Generator) {
                    $generator->controller = &$controller_instance;
                    $this->coroutine->start($generator);
                }
            } catch (\Exception $e) {
                call_user_func([$controller_instance, 'onExceptionHandle'], $e);
            }
        }
    }

    /**
     * websocket断开连接
     * @param $serv
     * @param $fd
     */
    public function onSwooleWSClose($serv, $fd)
    {
        $this->onSwooleClose($serv, $fd);
    }
}