<?php
/**
 * 包含http服务器
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-29
 * Time: 上午9:42
 */

namespace Server;


use Server\Components\Process\ProcessManager;
use Server\Components\SDHelp\SDHelpProcess;
use Server\CoreBase\ControllerFactory;
use Server\CoreBase\HttpInput;

abstract class SwooleWebSocketServer extends SwooleHttpServer
{
    /**
     * @var array
     */
    protected $fdRequest = [];
    protected $custom_handshake = false;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 启动
     * @throws \Exception
     */
    public function start()
    {
        if (!$this->portManager->websocket_enable) {
            parent::start();
            return;
        }
        $first_config = $this->portManager->getFirstTypePort();
        $set = $this->portManager->getProbufSet($first_config['socket_port']);
        if (array_key_exists('ssl_cert_file', $first_config)) {
            $set['ssl_cert_file'] = $first_config['ssl_cert_file'];
        }
        if (array_key_exists('ssl_key_file', $first_config)) {
            $set['ssl_key_file'] = $first_config['ssl_key_file'];
        }
        $socket_ssl = $first_config['socket_ssl'] ?? false;
        //开启一个websocket服务器
        if ($socket_ssl) {
            $this->server = new \swoole_websocket_server($first_config['socket_name'], $first_config['socket_port'], SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
        } else {
            $this->server = new \swoole_websocket_server($first_config['socket_name'], $first_config['socket_port']);
        }
        $this->setServerSet($set);
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
        $this->server->on('Shutdown', [$this, 'onSwooleShutdown']);
        if ($this->custom_handshake) {
            $this->server->on('handshake', [$this, 'onSwooleWSHandShake']);
        }
        $this->portManager->buildPort($this, $first_config['socket_port']);
        $this->beforeSwooleStart();
        $this->server->start();
    }

    /**
     * @param $serv
     */
    public function onSwooleWorkerStop($serv, $workerId)
    {
        parent::onSwooleWorkerStart($serv, $workerId);
        if (!$this->isTaskWorker()) {
            go(function () use ($workerId) {
                ProcessManager::getInstance()->getRpcCall(SDHelpProcess::class, true)->setData("wsRequest:$workerId", $this->fdRequest);
            });
        }
    }

    /**
     * @param $serv
     * @throws \Exception
     */
    public function onSwooleWorkerStart($serv, $workerId)
    {
        parent::onSwooleWorkerStart($serv, $workerId);
        if (!$this->isTaskWorker()) {
            $result = ProcessManager::getInstance()->getRpcCall(SDHelpProcess::class)->getData("wsRequest:$workerId");
            if ($result != null) {
                $this->fdRequest = $result;
            }
        }
    }

    /**
     * 是否自定义handshake
     * @param bool $custom_handshake
     */
    public function setCustomHandshake(bool $custom_handshake)
    {
        $this->custom_handshake = $custom_handshake;
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
     * @param $topic
     * @return bool
     * @throws \Exception
     */
    public function send($fd, $data, $ifPack = false, $topic = null)
    {
        if (!$this->portManager->websocket_enable) {
            return parent::send($fd, $data, $ifPack, $topic);
        }
        $fdinfo = $this->server->connection_info($fd);
        if (empty($fdinfo)) return false;
        $server_port = $fdinfo['server_port'];
        //允许数据监控的情况就pub
        if ($this->allow_MonitorFlowData) {
            $uid = $this->getUidFromFd($fd);
            if (!empty($uid)) {
                try {
                    get_instance()->pub('$SYS_CHANNEL/' . "$uid/send", $data);
                } catch (\Throwable $e) {

                }
            }
        }
        if ($ifPack) {
            $pack = $this->portManager->getPack($server_port);
            if ($pack != null) {
                $data = $pack->pack($data, $topic);
            }
        }
        if ($this->isWebSocket($fdinfo)) {
            return $this->server->push($fd, $data, $this->portManager->getOpCode($server_port));
        } else {
            return $this->server->send($fd, $data);
        }
    }

    /**
     * websocket连接上时
     * @param $server
     * @param $request
     * @throws \Throwable
     */
    public function onSwooleWSOpen($server, $request)
    {
        $this->portManager->eventConnect($request->fd, $request);
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
        $server_port = $this->getServerPort($fd);
        $uid = $this->getUidFromFd($fd);
        $pack = $this->portManager->getPack($server_port);
        //反序列化，出现异常断开连接
        try {
            $client_data = $pack->unPack($data);
        } catch (\Throwable $e) {
            $pack->errorHandle($e, $fd);
            return null;
        }
        //是否允许流量监控
        if ($this->allow_MonitorFlowData) {
            if (!empty($uid)) {
                try {
                    get_instance()->pub('$SYS_CHANNEL/' . "$uid/recv", $client_data);
                } catch (\Throwable $e) {
                }
            }
        }
        $middleware_names = $this->portManager->getMiddlewares($server_port);
        $context = [];
        $path = '';
        $middlewares = $this->middlewareManager->create($middleware_names, $context, [$fd, &$client_data]);
        //client_data进行处理
        try {
            $this->middlewareManager->before($middlewares);
            $route = $this->portManager->getRoute($server_port);
            try {
                $client_data = $route->handleClientData($client_data);
                $controller_name = $route->getControllerName();
                $method_name = $this->portManager->getMethodPrefix($server_port) . $route->getMethodName();
                $path = $route->getPath();
                $controller_instance = ControllerFactory::getInstance()->getController($controller_name);
                if ($controller_instance != null) {
                    $request = $this->fdRequest[$fd] ?? null;
                    if ($request != null) {
                        $controller_instance->setRequest($request);
                    }
                    $controller_instance->setContext($context);
                    $controller_instance->setClientData($uid, $fd, $client_data, $controller_name, $method_name, $route->getParams());
                } else {
                    throw new \Exception('no controller');
                }
            } catch (\Throwable $e) {
                $route->errorHandle($e, $fd);
            }
        } catch (\Exception $e) {
            //被中断
        }
        try {
            $this->middlewareManager->after($middlewares, $path);
        } catch (\Exception $e) {
            //被中断
        }
        $this->middlewareManager->destory($middlewares);
        if (Start::getDebug()) {
            secho("DEBUG", $context);
        }
        unset($context);
    }

    /**
     * websocket断开连接
     * @param $serv
     * @param $fd
     * @throws \Throwable
     */
    public function onSwooleWSClose($serv, $fd)
    {
        unset($this->fdRequest[$fd]);
        $this->portManager->eventClose($fd);
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
        $this->fdRequest[$request->fd] = $this->transformationRequest($request);
        $response->end();

        $this->server->defer(function () use ($request) {
            go(function () use ($request) {
                $this->onSwooleWSOpen($this->server, $request);
            });
        });
        return true;
    }

    /**
     * @param $request
     * @return \stdClass
     */
    private function transformationRequest($request)
    {
        $_arr = get_object_vars($request);
        $new_request = new \stdClass();
        foreach ($_arr as $key => $val) {
            $new_request->$key = $val;
        }
        return $new_request;
    }
}

