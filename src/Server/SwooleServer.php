<?php

namespace Server;

use Gelf\Publisher;
use Monolog\Handler\GelfHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Noodlehaus\Config;
use Server\Components\GrayLog\UdpTransport;
use Server\CoreBase\Child;
use Server\CoreBase\ControllerFactory;
use Server\CoreBase\ILoader;
use Server\CoreBase\Loader;
use Server\CoreBase\PortManager;
use Server\Coroutine\Coroutine;

/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-6-28
 * Time: 上午11:37
 */
abstract class SwooleServer extends Child
{
    /**
     * 配置文件版本
     */
    const config_version = 2;

    /**
     * 版本
     */
    const version = "2.4.7";

    /**
     * server name
     * @var string
     */
    public $name = '';
    /**
     * server user
     * @var string
     */
    public $user = '';
    /**
     * worker数量
     * @var int
     */
    public $worker_num = 0;
    public $task_num = 0;

    /**
     * 服务器到现在的毫秒数
     * @var int
     */
    public $tickTime;

    /**
     * 加载器
     * @var ILoader
     */
    public $loader;
    /**
     * Emitted when worker processes stoped.
     *
     * @var callback
     */
    public $onErrorHandel = null;
    /**
     * @var \swoole_server
     */
    public $server;
    /**
     * @var Config
     */
    public $config;
    /**
     * 日志
     * @var Logger
     */
    public $log;

    /**
     * @var PortManager
     */
    public $portManager;

    /**
     * 是否需要协程支持(默认开启)
     * @var bool
     */
    protected $needCoroutine = true;

    /**
     * 设置monolog的loghandler
     */
    public function setLogHandler()
    {
        $this->log = new Logger($this->config->get('log.log_name', 'SD'));
        switch ($this->config['log']['active']) {
            case "graylog":
                $this->log->setHandlers([new GelfHandler(new Publisher(new UdpTransport($this->config['log']['graylog']['ip'], $this->config['log']['graylog']['port'])),
                    $this->config['log']['log_level'])]);
                break;
            case "file":
                $this->log->pushHandler(new RotatingFileHandler(LOG_DIR . "/" . $this->name . '.log',
                    $this->config['log']['file']['log_max_files'],
                    $this->config['log']['log_level']));
                break;
        }
    }

    public function __construct()
    {
        $this->onErrorHandel = [$this, 'onErrorHandel'];
        Start::initServer($this);
        // 加载配置
        $this->config = new Config(CONFIG_DIR);
        $this->user = $this->config->get('server.set.user', '');
        $this->setLogHandler();
        register_shutdown_function(array($this, 'checkErrors'));
        set_error_handler(array($this, 'displayErrorHandler'));
        $this->portManager = new PortManager($this->config['ports']);
        if ($this->loader == null) {
            $this->loader = new Loader();
        }
    }

    /**
     * 设置自定义Loader
     * @param ILoader $loader
     */
    public function setLoader(ILoader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * 设置服务器配置参数
     * @return mixed
     */
    public function setServerSet($probuf_set)
    {
        $set = $this->config->get('server.set', []);
        if ($probuf_set != null) {
            $set = array_merge($set, $probuf_set);
        }
        $this->worker_num = $set['worker_num'];
        $this->task_num = $set['task_worker_num'];
        $set['daemonize'] = Start::getDaemonize();
        $this->server->set($set);
    }

    /**
     * 启动
     */
    public function start()
    {
        if ($this->portManager->tcp_enable) {
            $first_config = $this->portManager->getFirstTypePort();
            $this->server = new \swoole_server($first_config['socket_name'], $first_config['socket_port'], SWOOLE_PROCESS, $first_config['socket_type']);
            $this->server->on('Start', [$this, 'onSwooleStart']);
            $this->server->on('WorkerStart', [$this, 'onSwooleWorkerStart']);
            $this->server->on('connect', [$this, 'onSwooleConnect']);
            $this->server->on('receive', [$this, 'onSwooleReceive']);
            $this->server->on('close', [$this, 'onSwooleClose']);
            $this->server->on('WorkerStop', [$this, 'onSwooleWorkerStop']);
            $this->server->on('Task', [$this, 'onSwooleTask']);
            $this->server->on('Finish', [$this, 'onSwooleFinish']);
            $this->server->on('PipeMessage', [$this, 'onSwoolePipeMessage']);
            $this->server->on('WorkerError', [$this, 'onSwooleWorkerError']);
            $this->server->on('ManagerStart', [$this, 'onSwooleManagerStart']);
            $this->server->on('ManagerStop', [$this, 'onSwooleManagerStop']);
            $this->server->on('Packet', [$this, 'onSwoolePacket']);
            $this->setServerSet($this->portManager->getProbufSet($first_config['socket_port']));
            $this->portManager->buildPort($this, $first_config['socket_port']);
            $this->beforeSwooleStart();
            $this->server->start();
        } else {
            print_r("没有任何服务启动\n");
            exit(0);
        }
    }

    /**
     * start前的操作
     */
    public function beforeSwooleStart()
    {

    }

    /**
     * onSwooleStart
     * @param $serv
     */
    public function onSwooleStart($serv)
    {
        Start::setMasterPid($serv->master_pid, $serv->manager_pid);
    }

    /**
     * onSwooleWorkerStart
     * @param $serv
     * @param $workerId
     */
    public function onSwooleWorkerStart($serv, $workerId)
    {
        //清除apc缓存
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        Start::setWorketPid($serv->worker_pid);
        // 重新加载配置
        $this->config = $this->config->load(CONFIG_DIR);
        if (!$serv->taskworker) {//worker进程
            if ($this->needCoroutine) {//启动协程调度器
                Coroutine::init();
            }
            Start::setProcessTitle('SWD-Worker');
        } else {
            Start::setProcessTitle('SWD-Tasker');
        }
    }

    /**
     * onSwooleConnect
     * @param $serv
     * @param $fd
     */
    public function onSwooleConnect($serv, $fd)
    {

    }

    /**
     * 客户端有消息时
     * @param $serv
     * @param $fd
     * @param $from_id
     * @param $data
     * @param null $server_port
     * @return CoreBase\Controller|void
     */
    public function onSwooleReceive($serv, $fd, $from_id, $data, $server_port = null)
    {
        if (!Start::$testUnity) {
            $fdinfo = $serv->connection_info($fd);
            $server_port = $fdinfo['server_port'];
        }
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
            if (Start::$testUnity) {
                $fd = 'self';
                $uid = $fd;
            } else {
                $uid = $fdinfo['uid'] ?? 0;
            }
            $method_name = $this->config->get('tcp.method_prefix', '') . $route->getMethodName();
            $controller_instance->setClientData($uid, $fd, $client_data, $controller_name, $method_name);
            try {
                $call = [$controller_instance, &$method_name];
                if (!is_callable($call)) {
                    $method_name = 'defaultMethod';
                }
                Coroutine::startCoroutine($call, $route->getParams());
            } catch (\Exception $e) {
                call_user_func([$controller_instance, 'onExceptionHandle'], $e);
            }
        }
        return $controller_instance;
    }

    /**
     * onSwooleClose
     * @param $serv
     * @param $fd
     */
    public function onSwooleClose($serv, $fd)
    {

    }

    /**
     * onSwooleWorkerStop
     * @param $serv
     * @param $worker_id
     */
    public function onSwooleWorkerStop($serv, $worker_id)
    {

    }

    /**
     * onSwooleTask
     * @param $serv
     * @param $task_id
     * @param $from_id
     * @param $data
     * @return mixed
     */
    public function onSwooleTask($serv, $task_id, $from_id, $data)
    {

    }

    /**
     * onSwooleFinish
     * @param $serv
     * @param $task_id
     * @param $data
     */
    public function onSwooleFinish($serv, $task_id, $data)
    {

    }

    /**
     * onSwoolePipeMessage
     * @param $serv
     * @param $from_worker_id
     * @param $message
     */
    public function onSwoolePipeMessage($serv, $from_worker_id, $message)
    {

    }

    /**
     * onSwooleWorkerError
     * @param $serv
     * @param $worker_id
     * @param $worker_pid
     * @param $exit_code
     */
    public function onSwooleWorkerError($serv, $worker_id, $worker_pid, $exit_code)
    {
        $data = ['worker_id' => $worker_id,
            'worker_pid' => $worker_pid,
            'exit_code' => $exit_code];
        $log = "WORKER Error ";
        $log .= json_encode($data);
        $this->log->alert($log);
        if ($this->onErrorHandel != null) {
            call_user_func($this->onErrorHandel, '【！！！】服务器进程异常退出', $log);
        }
    }

    /**
     * ManagerStart
     * @param $serv
     */
    public function onSwooleManagerStart($serv)
    {
        Start::setProcessTitle('SWD-Manager');
    }

    /**
     * ManagerStop
     * @param $serv
     */
    public function onSwooleManagerStop($serv)
    {

    }

    /**
     * onPacket(UDP)
     * @param $server
     * @param string $data
     * @param array $client_info
     */
    public function onSwoolePacket($server, $data, $client_info)
    {

    }

    /**
     * 包装SerevrMessageBody消息
     * @param $type
     * @param $message
     * @param string $func
     * @return string
     */
    public function packSerevrMessageBody($type, $message, string $func = null)
    {
        $data['type'] = $type;
        $data['message'] = $message;
        $data['func'] = $func;
        return $data;
    }

    /**
     * 魔术方法
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->server, $name), $arguments);
    }

    /**
     * 全局错误监听
     * @param $error
     * @param $error_string
     * @param $filename
     * @param $line
     * @param $symbols
     */
    public function displayErrorHandler($error, $error_string, $filename, $line, $symbols)
    {
        $log = "WORKER Error ";
        $log .= "$error_string ($filename:$line)";
        $this->log->error($log);
        if ($this->onErrorHandel != null) {
            call_user_func($this->onErrorHandel, '服务器发生严重错误', $log);
        }
    }

    /**
     * Check errors when current process exited.
     *
     * @return void
     */
    public function checkErrors()
    {
        $log = "WORKER EXIT UNEXPECTED ";
        $error = error_get_last();
        if (isset($error['type'])) {
            switch ($error['type']) {
                case E_ERROR :
                case E_PARSE :
                case E_CORE_ERROR :
                case E_COMPILE_ERROR :
                    $message = $error['message'];
                    $file = $error['file'];
                    $line = $error['line'];
                    $log .= "$message ($file:$line)\nStack trace:\n";
                    $trace = debug_backtrace();
                    foreach ($trace as $i => $t) {
                        if (!isset($t['file'])) {
                            $t['file'] = 'unknown';
                        }
                        if (!isset($t['line'])) {
                            $t['line'] = 0;
                        }
                        if (!isset($t['function'])) {
                            $t['function'] = 'unknown';
                        }
                        $log .= "#$i {$t['file']}({$t['line']}): ";
                        if (isset($t['object']) and is_object($t['object'])) {
                            $log .= get_class($t['object']) . '->';
                        }
                        $log .= "{$t['function']}()\n";
                    }
                    if (isset($_SERVER['REQUEST_URI'])) {
                        $log .= '[QUERY] ' . $_SERVER['REQUEST_URI'];
                    }
                    $this->log->error($log);
                    if ($this->onErrorHandel != null) {
                        call_user_func($this->onErrorHandel, '服务器发生崩溃事件', $log);
                    }
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Get socket name.
     *
     * @return string
     */
    public function getSocketName()
    {
        return $this->socket_name ? lcfirst($this->socket_name . ":" . $this->port) : 'none';
    }

    /**
     * 是否是task进程
     * @return bool
     */
    public function isTaskWorker()
    {
        return $this->server->taskworker;
    }

    /**
     * 判断是tcp还是websocket进行发送
     * @param $fd
     * @param $data
     * @param bool $ifPack
     */
    public function send($fd, $data, $ifPack = false)
    {
        if (!$this->server->exist($fd)) {
            return;
        }
        if ($ifPack) {
            $fdinfo = $this->server->connection_info($fd);
            $server_port = $fdinfo['server_port'];
            $pack = $this->portManager->getPack($server_port);
            if ($pack != null) {
                $data = $pack->pack($data);
            }
        }
        $this->server->send($fd, $data);
    }

    /**
     * 服务器主动关闭链接
     * close fd
     * @param $fd
     */
    public function close($fd)
    {
        $this->server->close($fd);
    }


    /**
     * 错误处理函数
     * @param $msg
     * @param $log
     */
    public function onErrorHandel($msg, $log)
    {
        print_r($msg . "\n");
        print_r($log . "\n");
    }
}
