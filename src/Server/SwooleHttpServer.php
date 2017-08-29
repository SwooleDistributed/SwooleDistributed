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
use Server\CoreBase\ControllerFactory;
use Server\Coroutine\Coroutine;

abstract class SwooleHttpServer extends SwooleServer
{
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
     * 启动
     */
    public function start()
    {
        if (!$this->portManager->http_enable) {
            parent::start();
            return;
        }
        $first_config = $this->portManager->getFirstTypePort();
        //开启一个http服务器
        $this->server = new \swoole_http_server($first_config['socket_name'], $first_config['socket_port']);
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
        $this->setServerSet($this->portManager->getProbufSet($first_config['socket_port']));
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
            if (!isset($this->cache404)) {//内存缓存404页面
                $template = $this->loader->view('server::error_404');
                $this->cache404 = $template->render();
            }
            $response->end($this->cache404);
            return;
        }
        $extension = pathinfo($route->getPath(), PATHINFO_EXTENSION);
        if ($path=="/") {//寻找主页
            $www_path = $this->getHostRoot($host) . $this->getHostIndex($host);
            $result = httpEndFile($www_path, $request, $response);
            if (!$result) {
                $error_404 = true;
            } else {
                return;
            }
        }else if(!empty($extension)){//有后缀
            $www_path = $this->getHostRoot($host) . $route->getPath();
            $result = httpEndFile($www_path, $request, $response);
            if (!$result) {
                $error_404 = true;
            }
        }
        else {
            $controller_name = $route->getControllerName();
            $controller_instance = ControllerFactory::getInstance()->getController($controller_name);
            if ($controller_instance != null) {
                if ($route->getMethodName() == '_consul_health') {//健康检查
                    $response->end('ok');
                    $controller_instance->destroy();
                    return;
                }
                $method_name = $this->config->get('http.method_prefix', '') . $route->getMethodName();
                $call = [$controller_instance, &$method_name];
                if (!is_callable($call)) {
                    $method_name = 'defaultMethod';
                }
                try {
                    $controller_instance->setRequestResponse($request, $response, $controller_name, $method_name);
                    Coroutine::startCoroutine($call, $route->getParams());
                    return;
                } catch (\Exception $e) {
                    call_user_func([$controller_instance, 'onExceptionHandle'], $e);
                }
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
        $index = $this->config['http']['root'][$host]['index']??'index.html';
        return $index;
    }
}