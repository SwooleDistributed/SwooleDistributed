<?php

namespace Server;

use Gelf\Publisher;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\GelfHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use Noodlehaus\Config;
use Server\Components\Backstage\BackstageHelp;
use Server\Components\Event\EventDispatcher;
use Server\Components\GrayLog\UdpTransport;
use Server\Components\log\SDJsonFormatter;
use Server\Components\Middleware\MiddlewareManager;
use Server\Components\Process\ProcessRPC;
use Server\CoreBase\ControllerFactory;
use Server\CoreBase\ILoader;
use Server\CoreBase\Loader;
use Server\CoreBase\PortManager;
use Whoops\Exception\ErrorException;

/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-6-28
 * Time: 上午11:37
 */
abstract class SwooleServer extends ProcessRPC
{
    /**
     * 配置文件版本
     */
    const config_version = 3;

    /**
     * 版本
     */
    const version = "3.6.2";

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
     * @var MiddlewareManager
     */
    protected $middlewareManager;

    /**
     * @var int
     */
    public $workerId;

    /**
     * 共享内存表
     * @var \swoole_table
     */
    protected $uid_fd_table;
    /**
     * @var \swoole_table
     */
    protected $fd_uid_table;

    /**
     * @var int
     */
    protected $max_connection;

    /**
     * @var bool
     */
    protected $allow_MonitorFlowData;

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
                $handel = new RotatingFileHandler(LOG_DIR . "/" . $this->name . '.log',
                    $this->config['log']['file']['log_max_files'],
                    $this->config['log']['log_level']);
                $handel->setFormatter(new JsonFormatter());
                $this->log->pushHandler($handel);
                break;
            case "syslog":
                $handel = new SyslogHandler($this->config['log']['syslog']['ident']);
                $handel->setFormatter(new SDJsonFormatter());
                $this->log->pushHandler($handel);
                break;
        }
    }

    /**
     * SwooleServer constructor.
     * @throws \Noodlehaus\Exception\EmptyDirectoryException
     */
    public function __construct()
    {
        $this->onErrorHandel = [$this, 'onErrorHandel'];
        $this->setConfig();
        $this->middlewareManager = new MiddlewareManager();
        $this->user = $this->config->get('server.set.user', '');
        $this->setLogHandler();
        set_error_handler([$this, 'displayErrorHandler'], E_ALL | E_STRICT);
        set_exception_handler('displayExceptionHandler');
        $this->portManager = new PortManager($this->config['ports']);
        if ($this->loader == null) {
            $this->loader = new Loader();
        }
        $this->allow_MonitorFlowData = $this->config->get("allow_MonitorFlowData", false);
    }

    /**
     * 加载配置
     * @throws \Noodlehaus\Exception\EmptyDirectoryException
     */
    protected function setConfig()
    {
        $this->config = new Config(getConfigDir());
        BackstageHelp::init();
    }

    /**
     * 设置自定义Loader
     * @param ILoader $loader
     */
    protected function setLoader(ILoader $loader)
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
        swoole_async_set([
            'socket_buffer_size' => 1024 * 1024 * 1024
        ]);
    }

    /**
     * 启动
     */
    public function start()
    {
        if ($this->portManager->tcp_enable) {
            $first_config = $this->portManager->getFirstTypePort();
            $set = $this->portManager->getProbufSet($first_config['socket_port']);
            if (array_key_exists('ssl_cert_file', $first_config)) {
                $set['ssl_cert_file'] = $first_config['ssl_cert_file'];
            }
            if (array_key_exists('ssl_key_file', $first_config)) {
                $set['ssl_key_file'] = $first_config['ssl_key_file'];
            }
            $socket_ssl = $first_config['socket_ssl'] ?? false;
            if ($socket_ssl) {
                $this->server = new \swoole_server($first_config['socket_name'], $first_config['socket_port'], SWOOLE_PROCESS, $first_config['socket_type'] | SWOOLE_SSL);
            } else {
                $this->server = new \swoole_server($first_config['socket_name'], $first_config['socket_port'], SWOOLE_PROCESS, $first_config['socket_type']);
            }
            $this->setServerSet($set);
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
            $this->server->on('Shutdown', [$this, 'onSwooleShutdown']);
            $this->portManager->buildPort($this, $first_config['socket_port']);
            $this->beforeSwooleStart();
            $this->server->start();
        } else {
            secho("SYS", "没有任何服务启动");
            exit(0);
        }
    }

    /**
     * start前的操作
     */
    public function beforeSwooleStart()
    {
        //创建uid->fd共享内存表
        $this->createUidTable();
    }

    /**
     * 创建uid->fd共享内存表
     */
    public function createUidTable()
    {
        $this->max_connection = $this->config['server']['set']['max_connection'] ?? 65536;
        $this->uid_fd_table = new \swoole_table($this->max_connection);
        $this->uid_fd_table->column('fd', \swoole_table::TYPE_INT, 8);
        $this->uid_fd_table->create();

        $this->fd_uid_table = new \swoole_table($this->max_connection);
        $this->fd_uid_table->column('uid', \swoole_table::TYPE_STRING, 50);
        $this->fd_uid_table->create();
    }

    /**
     * onSwooleStart
     * @param $serv
     */
    public function onSwooleStart($serv)
    {
        setTimezone();
        Start::setProcessTitle(getServerName() . '-Master');
        if (Start::getDebug()) {
            secho("SYS", "工作在DEBUG模式");
        }
    }

    /**
     * onSwooleWorkerStart
     * @param $serv
     * @param $workerId
     */
    public function onSwooleWorkerStart($serv, $workerId)
    {
        setTimezone();
        $this->workerId = $workerId;
        //清除apc缓存
        if (function_exists('apc_clear_cache')) {
            apc_clear_cache();
        }
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        // 重新加载配置
        $this->config = $this->config->load(getConfigDir());
        if (!$serv->taskworker) {//worker进程
            Start::setProcessTitle(getServerName() . "-Worker");
        } else {
            Start::setProcessTitle(getServerName() . "-Tasker");
        }
    }

    /**
     * onSwooleConnect
     * @param $serv
     * @param $fd
     * @throws \Throwable
     */
    public function onSwooleConnect($serv, $fd)
    {
        $this->portManager->eventConnect($fd);
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
            $server_port = $this->getServerPort($fd);
            $uid = $this->getUidFromFd($fd);
        } else {
            $fd = 'self';
            $uid = $fd;
        }
        $pack = $this->portManager->getPack($server_port);
        //反序列化，出现异常断开连接
        try {
            $client_data = $pack->unPack($data);
        } catch (\Throwable $e) {
            $pack->errorHandle($e, $fd);
            return;
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
     * @return string
     */
    abstract function getEventControllerName();

    /**
     * @return string
     */
    abstract function getCloseMethodName();

    /**
     * @return string
     */
    abstract function getConnectMethodName();

    /**
     * onSwooleClose
     * @param $serv
     * @param $fd
     * @throws \Throwable
     */
    public function onSwooleClose($serv, $fd)
    {
        $this->portManager->eventClose($fd);
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
     * onSwooleShutdown
     * @param $serv
     */
    public function onSwooleShutdown($serv)
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
     * @throws \Exception
     */
    public function onSwoolePipeMessage($serv, $from_worker_id, $message)
    {
        switch ($message['type']) {
            case SwooleMarco::PROCESS_RPC_RESULT:
                EventDispatcher::getInstance()->dispatch($message['message']['token'], $message['message']['result'], true);
                break;
            case SwooleMarco::PROCESS_RPC:
                $this->processPpcRun($message['message']);
                break;
            default:
                if (!empty($message['func'])) {
                    $message['func']($message['message']);
                }
        }
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
            sd_call_user_func($this->onErrorHandel, '【！！！】服务器进程异常退出', $log);
        }
    }

    /**
     * ManagerStart
     * @param $serv
     */
    public function onSwooleManagerStart($serv)
    {
        Start::setProcessTitle(getServerName() . '-Manager');
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
    public function packServerMessageBody($type, $message, string $func = null)
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
        return sd_call_user_func_array(array($this->server, $name), $arguments);
    }


    /**
     * @param $error
     * @param $error_string
     * @param $filename
     * @param $line
     * @param $symbols
     * @throws ErrorException
     */
    public function displayErrorHandler($error, $error_string, $filename, $line, $symbols)
    {
        throw new ErrorException($error_string, $error, 1, $filename, $line);
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
     * 获取workerId
     * @return int
     */
    public function getWorkerId()
    {
        return $this->workerId;
    }

    /**
     * 是不是worker进程
     * @param null $worker_id
     * @return bool
     */
    public function isWorker($worker_id = null)
    {
        if ($worker_id == null) {
            $worker_id = $this->workerId;
        }
        return $worker_id < $this->worker_num ? true : false;
    }

    /**
     * 是否是task进程
     * @return bool
     */
    public function isTaskWorker()
    {
        return $this->server->taskworker ?? false;
    }

    /**
     * 是不是用户进程
     * @param null $worker_id
     * @return bool
     */
    public function isUserProcess($worker_id = null)
    {
        //一定要===
        if ($worker_id === null) {
            $worker_id = $this->workerId;
        }
        return $worker_id >= $this->worker_num + $this->task_num ? true : false;
    }

    /**
     * 判断是tcp还是websocket进行发送
     * @param $fd
     * @param $data
     * @param bool $ifPack
     * @param null $topic
     * @return bool
     */
    public function send($fd, $data, $ifPack = false, $topic = null)
    {
        if (!$this->server->exist($fd)) {
            return false;
        }
        if ($ifPack) {
            $pack = $this->portManager->getPackFromFd($fd);
            if ($pack != null) {
                $data = $pack->pack($data, $topic);
            }
        }
        return $this->server->send($fd, $data);
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
        secho("ERROR", $msg);
        secho("ERROR", $log);
    }

    /**
     * @param $fd
     * @return mixed
     */
    public function getFdInfo($fd)
    {
        $fdinfo = $this->server->connection_info($fd);
        return $fdinfo;
    }

    /**
     * @param $fd
     * @return mixed
     */
    public function getServerPort($fd)
    {
        return $this->server->connection_info($fd)['server_port'];
    }

    /**
     *
     * @param $fd
     */
    public function protect($fd)
    {
        $this->server->protect($fd);
    }

    /**
     * 通过Uid获取fd
     * @param $uid
     * @return mixed
     */
    public function getFdFromUid($uid)
    {
        return $this->uid_fd_table->get($uid, 'fd');
    }

    /**
     * 通过fd获取uid
     * @param $fd
     * @return mixed
     */
    public function getUidFromFd($fd)
    {
        return $this->fd_uid_table->get($fd, 'uid');
    }
}
