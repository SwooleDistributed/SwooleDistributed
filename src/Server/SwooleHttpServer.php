<?php
/**
 * 包含http服务器
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-29
 * Time: 上午9:42
 */

namespace Server;


use League\Plates\Engine;
use Server\Components\Consul\ConsulHelp;
use Server\CoreBase\ControllerFactory;

abstract class SwooleHttpServer extends SwooleServer
{
    /**
     * 模板引擎
     * @var Engine
     */
    public $templateEngine;
    protected $http_method_prefix;
    protected $cache404;
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
        $this->setTemplateEngine();
        $this->http_method_prefix = $this->config->get('http.method_prefix', '');
        $template = $this->loader->view('server::error_404');
        $this->cache404 = $template->render();
    }

    /**
     * 设置模板引擎
     */
    public function setTemplateEngine()
    {
        $this->templateEngine = new Engine();
        $this->templateEngine->addFolder('server', SERVER_DIR . '/Views');
        $this->templateEngine->addFolder('app', APP_DIR . '/Views');
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
            $fdinfo = $this->server->connection_info($request->fd);
            $server_port = $fdinfo['server_port'];
        }
        $route = $this->portManager->getRoute($server_port);
        $error_404 = false;
        $controller_instance = null;
        $route->handleClientRequest($request);
        list($host) = explode(':', $request->header['host']??'');
        $path = $route->getPath();
        if($path=='/404'){
            $response->header('HTTP/1.1', '404 Not Found');
            $response->end($this->cache404);
            return;
        }
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($path=="/") {//寻找主页
            $www_path = $this->getHostRoot($host) . $this->getHostIndex($host);
            $result = httpEndFile($www_path, $request, $response);
            if (!$result) {
                $error_404 = true;
            } else {
                return;
            }
        }else if(!empty($extension)){//有后缀
            $www_path = $this->getHostRoot($host) . $path;
            $result = httpEndFile($www_path, $request, $response);
            if (!$result) {
                $error_404 = true;
            }
        }
        else {
            $controller_name = $route->getControllerName();
            $controller_instance = ControllerFactory::getInstance()->getController($controller_name);
            if ($controller_instance != null) {
                if ($route->getMethodName() == ConsulHelp::HEALTH) {//健康检查
                    $response->end('ok');
                    $controller_instance->destroy();
                    return;
                }
                $method_name = $this->http_method_prefix . $route->getMethodName();
                $controller_instance->setRequestResponse($request, $response, $controller_name, $method_name, $route->getParams());
                return;
            } else {
                $error_404 = true;
            }
        }
        if ($error_404) {
            if ($controller_instance != null) {
                $controller_instance->destroy();
            }
            //重定向到404
            $response->status(302);
            $location = 'http://'.$request->header['host']."/".'404';
            $response->header('Location',$location);
            $response->end('');
        }
    }

    /**
     * 获得host对应的根目录
     * @param $host
     * @return string
     */
    public function getHostRoot($host)
    {
        $root_path = $this->config['http']['root'][$host]['root']??'';
        if (empty($root_path)) {
            $root_path = $this->config['http']['root']['default']['root']??'';
        }
        if (!empty($root_path)) {
            $root_path = WWW_DIR . "/$root_path/";
        } else {
            $root_path = WWW_DIR . "/";
        }
        return $root_path;
    }

    /**
     * 返回host对应的默认文件
     * @param $host
     * @return mixed|null
     */
    public function getHostIndex($host)
    {
        $index = $this->config['http']['root'][$host]['index'] ?? $this->config['http']['root']['default']['index'] ?? 'index.html';
        return $index;
    }
}