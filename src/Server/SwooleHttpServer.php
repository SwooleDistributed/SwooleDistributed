<?php
/**
 * 包含http服务器
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-29
 * Time: 上午9:42
 */

namespace Server;


use League\Plates\Engine;
use Server\CoreBase\ControllerFactory;
use Server\CoreBase\GeneratorContext;

abstract class SwooleHttpServer extends SwooleServer
{
    /**
     * http host
     * @var string
     */
    public $http_socket_name;
    /**
     * http port
     * @var integer
     */
    public $http_port;
    /**
     * http使能
     * @var bool
     */
    public $http_enable;
    /**
     * 模板引擎
     * @var Engine
     */
    public $templateEngine;

    public function __construct()
    {
        parent::__construct();
        //view dir
        $view_dir = APP_DIR . '/Views';
        if (!is_dir($view_dir)) {
            echo "app目录下不存在Views目录，请创建。\n";
            exit();
        }
    }

    /**
     * 设置配置
     */
    public function setConfig()
    {
        parent::setConfig();
        $this->http_enable = $this->config['http_server']['enable'];
        $this->http_socket_name = $this->config['http_server']['socket'];
        $this->http_port = $this->config['http_server']['port'];
    }

    /**
     * 启动
     */
    public function start()
    {
        if (!$this->http_enable) {
            parent::start();
            return;
        }
        //开启一个http服务器
        $this->server = new \swoole_http_server($this->http_socket_name, $this->http_port);
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
        $set = $this->setServerSet();
        $set['daemonize'] = self::$daemonize ? 1 : 0;
        $this->server->set($set);
        if ($this->tcp_enable) {
            $this->port = $this->server->listen($this->socket_name, $this->port, $this->socket_type);
            $this->port->set($set);
            $this->port->on('connect', [$this, 'onSwooleConnect']);
            $this->port->on('receive', [$this, 'onSwooleReceive']);
            $this->port->on('close', [$this, 'onSwooleClose']);
            $this->port->on('Packet', [$this, 'onSwoolePacket']);
        }
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
        $this->setTemplateEngine();
    }

    /**
     * 设置模板引擎
     */
    public function setTemplateEngine()
    {
        $this->templateEngine = new Engine();
        $this->templateEngine->addFolder('server', __DIR__ . '/Views');
        $this->templateEngine->addFolder('app', __DIR__ . '/../app/Views');
        $this->templateEngine->registerFunction('get_www', 'get_www');
    }

    /**
     * http服务器发来消息
     * @param $request
     * @param $response
     */
    public function onSwooleRequest($request, $response)
    {
        $error_404 = false;
        $this->route->handleClientRequest($request);
        if ($this->route->getPath() == '/') {
            $www_path = WWW_DIR . '/' . $this->config->get('http.index', 'index.html');
            $result = httpEndFile($www_path, $response);
            if (!$result) {
                $error_404 = true;
            } else {
                return;
            }
        } else {
            $controller_name = $this->route->getControllerName();
            $controller_instance = ControllerFactory::getInstance()->getController($controller_name);
            if ($controller_instance != null) {
                $method_name = $this->config->get('http.method_prefix', '') . $this->route->getMethodName();
                if (method_exists($controller_instance, $method_name)) {
                    try {
                        $controller_instance->setRequestResponse($request, $response, $controller_name, $method_name);
                        $generator = call_user_func([$controller_instance, $method_name], $this->route->getParams());
                        if ($generator instanceof \Generator) {
                            $generatorContext = new GeneratorContext();
                            $generatorContext->setController($controller_instance, $controller_name, $method_name);
                            $this->coroutine->start($generator, $generatorContext);
                        }
                        return;
                    } catch (\Exception $e) {
                        call_user_func([$controller_instance, 'onExceptionHandle'], $e);
                    }
                } else {
                    $error_404 = true;
                }
            } else {
                $error_404 = true;
            }
        }
        if ($error_404) {
            if ($controller_instance != null) {
                $controller_instance->destroy();
            }
            //先根据path找下www目录
            $www_path = WWW_DIR . $this->route->getPath();
            $result = httpEndFile($www_path, $response);
            if (!$result) {
                $response->header('HTTP/1.1', '404 Not Found');
                if (!isset($this->cache404)) {//内存缓存404页面
                    $template = $this->loader->view('server::error_404');
                    $this->cache404 = $template->render();
                }
                $response->end($this->cache404);
            }
        }
    }
}