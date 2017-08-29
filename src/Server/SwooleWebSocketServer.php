<?php
/**
 * 包含http服务器
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-29
 * Time: 上午9:42
 */

namespace Server;


use Server\CoreBase\ControllerFactory;
use Server\CoreBase\HttpInput;
use Server\Coroutine\Coroutine;

abstract class SwooleWebSocketServer extends SwooleHttpServer
{
    /**
     * @var array
     */
    protected $fdRequest = [];
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 启动
     */
    public function start()
    {
        if (!$this->portManager->websocket_enable) {
            parent::start();
            return;
        }
        $first_config = $this->portManager->getFirstTypePort();
        //开启一个websocket服务器
        $this->server = new \swoole_websocket_server($first_config['socket_name'], $first_config['socket_port']);
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
        $this->server->on('handshake', [$this, 'onSwooleWSHandShake']);
        $this->setServerSet($this->portManager->getProbufSet($first_config['socket_port']));
        $this->portManager->buildPort($this, $first_config['socket_port']);
        $this->beforeSwooleStart();
        $this->server->start();
    }

    /**
     * 判断这个fd是不是一个WebSocket连接，用于区分tcp和websocket
     * 握手后才识别为websocket
     * @param $fdinfo
     * @return bool
     * @throws \Exception
     * @internal param $fd
     */
    public function isWebSocket($fdinfo)
    {
        if (empty($fdinfo)) {
            throw new \Exception('fd not exist');
        }
        if (array_key_exists('websocket_status', $fdinfo) && $fdinfo['websocket_status'] == WEBSOCKET_STATUS_FRAME) {
            return $fdinfo['server_port'];
        }
        return false;
    }

    /**
     * 判断是tcp还是websocket进行发送
     * @param $fd
     * @param $data
     * @param bool $ifPack
     */
    public function send($fd, $data, $ifPack = false)
    {
        if (!$this->portManager->websocket_enable) {
            parent::send($fd, $data, $ifPack);
            return;
        }
        if (!$this->server->exist($fd)) {
            return;
        }
        $fdinfo = $this->server->connection_info($fd);
        $server_port = $fdinfo['server_port'];
        if ($ifPack) {
            $pack = $this->portManager->getPack($server_port);
            if ($pack != null) {
                $data = $pack->pack($data);
            }
        }
        if ($this->isWebSocket($fdinfo)) {
            $this->server->push($fd, $data, $this->portManager->getOpCode($server_port));
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
     * websocket合并后完整的消息
     * @param $serv
     * @param $fd
     * @param $data
     * @return CoreBase\Controller
     */
    public function onSwooleWSAllMessage($serv, $fd, $data)
    {
        $fdinfo = $serv->connection_info($fd);
        $server_port = $fdinfo['server_port'];
        $route = $this->portManager->getRoute($server_port);
        $pack = $this->portManager->getPack($server_port);
        //反序列化，出现异常断开连接
        try {
            $client_data = $pack->unPack($data);
        } catch (\Exception $e) {
            $pack->errorHandle($e, $fd);
            return null;
        }
        //client_data进行处理
        try {
            $client_data = $route->handleClientData($client_data);
        } catch (\Exception $e) {
            $route->errorHandle($e, $fd);
            return null;
        }
        $controller_name = $route->getControllerName();
        $controller_instance = ControllerFactory::getInstance()->getController($controller_name);
        if ($controller_instance != null) {
            $uid = $serv->connection_info($fd)['uid']??0;
            $method_name = $this->config->get('websocket.method_prefix', '') . $route->getMethodName();
            $call = [$controller_instance, &$method_name];
            if (!is_callable($call)) {
                $method_name = 'defaultMethod';
            }
            $request = $this->fdRequest[$fd] ?? null;
            if ($request != null) {
                $controller_instance->setRequest($request);
            }
            $controller_instance->setClientData($uid, $fd, $client_data, $controller_name, $method_name);
            try {
                Coroutine::startCoroutine($call, $route->getParams());
            } catch (\Exception $e) {
                call_user_func([$controller_instance, 'onExceptionHandle'], $e);
            }
        }
        return $controller_instance;
    }

    /**
     * websocket断开连接
     * @param $serv
     * @param $fd
     */
    public function onSwooleWSClose($serv, $fd)
    {
        unset($this->fdRequest[$fd]);
        $this->onSwooleClose($serv, $fd);
    }

    /**
     * 可以在这验证WebSocket连接,return true代表可以握手，false代表拒绝
     * @param HttpInput $httpInput
     * @return bool
     */
    abstract public function onWebSocketHandCheck(HttpInput $httpInput);

    /**
     * @var HttpInput
     */
    protected $webSocketHttpInput;

    /**
     * ws握手
     * @param $request
     * @param $response
     * @return bool
     */
    public function onSwooleWSHandShake(\swoole_http_request $request, \swoole_http_response $response)
    {
        if ($this->webSocketHttpInput == null) {
            $this->webSocketHttpInput = new HttpInput();
        }
        $this->webSocketHttpInput->set($request);
        $result = $this->onWebSocketHandCheck($this->webSocketHttpInput);
        if (!$result) {
            $response->end();
            return false;
        }
        // websocket握手连接算法验证
        $secWebSocketKey = $request->header['sec-websocket-key'];
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
        if (0 === preg_match($patten, $secWebSocketKey) || 16 !== strlen(base64_decode($secWebSocketKey))) {
            $response->end();
            return false;
        }
        $key = base64_encode(sha1(
            $request->header['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
            true
        ));

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $key,
            'Sec-WebSocket-Version' => '13',
        ];

        if (isset($request->header['sec-websocket-protocol'])) {
            $headers['Sec-WebSocket-Protocol'] = $request->header['sec-websocket-protocol'];
        }

        foreach ($headers as $key => $val) {
            $response->header($key, $val);
        }

        $response->status(101);
        $this->fdRequest[$request->fd] = $request;
        $response->end();

        $this->server->defer(function () use ($request) {
            $this->onSwooleWSOpen($this->server, $request);
        });
        return true;
    }
}