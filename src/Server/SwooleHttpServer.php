<?php
/**
 * 包含http服务器
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-29
 * Time: 上午9:42
 */

namespace Server;

use Server\Components\Blade\Blade;
use Server\Components\Consul\ConsulHelp;
use Server\Components\Whoops\Handler\SDPageHandler;
use Server\CoreBase\ControllerFactory;
use Whoops\Run;

abstract class SwooleHttpServer extends SwooleServer
{
    /**
     * 模板引擎
     * @var Components\Blade\Blade
     */
    public $templateEngine;
    /**
     * @var Run
     */
    public $whoops;

    protected $cachePath;
    public function __construct()
    {
        parent::__construct();
        //view dir
        $view_dir = APP_DIR . '/Views';
        if (!is_dir($view_dir)) {
            secho("STA", "app目录下不存在Views目录，请创建。");
            exit();
        }
        $this->cachePath = BIN_DIR . "/bladeCache";
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath);
        }
    }

    /**
     * 启动
     */
    public function start()
    {
        if (!$this->portManager->http_enable) {
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
        //开启一个http服务器
        if ($socket_ssl) {
            $this->server = new \swoole_http_server($first_config['socket_name'], $first_config['socket_port'], SWOOLE_PROCESS, SWOOLE_SOCK_TCP | SWOOLE_SSL);
        } else {
            $this->server = new \swoole_http_server($first_config['socket_name'], $first_config['socket_port']);
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
        $this->server->on('Shutdown', [$this, 'onSwooleShutdown']);
        $this->portManager->buildPort($this, $first_config['socket_port']);
        $this->beforeSwooleStart();
        $this->server->start();
    }


    /**
     * workerStart
     * @param $serv
     * @param $workerId
     */
    public function onSwooleWorkerStart($serv, $workerId)
    {
        parent::onSwooleWorkerStart($serv, $workerId);
        if (!$this->isTaskWorker()) {
            $this->whoops = new Run();
            $this->whoops->writeToOutput(false);
            $this->whoops->allowQuit(false);
            $handler = new SDPageHandler();
            $handler->setPageTitle("出现错误了");
            $this->whoops->pushHandler($handler);
            $this->setTemplateEngine();
        }
    }

    /**
     * 设置模板引擎
     */
    public function setTemplateEngine()
    {
        $this->templateEngine = new Blade($this->cachePath);
        $this->templateEngine->addNamespace("server", SERVER_DIR . '/Views');
        $this->templateEngine->addNamespace("app", APP_DIR . '/Views');
    }

    public function getWhoops()
    {
        return $this->whoops;
    }

    /**
     * @return Blade
     */
    public function getTemplateEngine()
    {
        return $this->templateEngine;
    }

    /**
     * http服务器发来消息
     * @param $request
     * @param $response
     */
    public function onSwooleRequest($request, $response)
    {
        if (Start::$testUnity) {
            $server_port = $request->server_port;
        } else {
            $server_port = $this->getServerPort($request->fd);
        }

        $middleware_names = $this->portManager->getMiddlewares($server_port);
        $context = [];
        $path = $request->server['path_info'];
        $middlewares = $this->middlewareManager->create($middleware_names, $context, [$request, $response], true);
        //before
        try {
            $this->middlewareManager->before($middlewares);
            //client_data进行处理
            $route = $this->portManager->getRoute($server_port);
            try {
                $route->handleClientRequest($request);
                $controller_name = $route->getControllerName();
                $method_name = $this->portManager->getMethodPrefix($server_port) . $route->getMethodName();
                $path = $route->getPath();
                if ($route->getMethodName() == ConsulHelp::HEALTH) {//健康检查
                    $response->end('ok');
                } else {
                    $controller_instance = ControllerFactory::getInstance()->getController($controller_name);
                    if ($controller_instance != null) {
                        $controller_instance->setContext($context);
                        $controller_instance->setRequestResponse($request, $response, $controller_name, $method_name, $route->getParams());
                    } else {
                        throw new \Exception('no controller');
                    }
                }
            } catch (\Throwable $e) {
                $route->errorHttpHandle($e, $request, $response);
            }
        } catch (\Throwable $e) {
        }
        //after
        try {
            $this->middlewareManager->after($middlewares, $path);
        } catch (\Throwable $e) {
        }
        $this->middlewareManager->destory($middlewares);
        if (Start::getDebug()) {
            secho("DEBUG", $context);
        }
        unset($context);
    }

}