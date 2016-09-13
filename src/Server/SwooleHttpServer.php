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

abstract class SwooleHttpServer extends SwooleServer
{
    public $http_socket_name;
    public $http_port;
    public $port;
    /**
     * 模板引擎
     * @var Engine
     */
    public $templateEngine;

    public function start()
    {
        if (empty($this->http_socket_name) || empty($this->http_port)) {
            print_r("not use http server.\n");
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
        $this->port = $this->server->listen($this->socket_name, $this->port, $this->socket_type);
        $this->port->set($set);
        $this->port->on('connect', [$this, 'onSwooleConnect']);
        $this->port->on('receive', [$this, 'onSwooleReceive']);
        $this->port->on('close', [$this, 'onSwooleClose']);
        $this->port->on('Packet', [$this, 'onSwoolePacket']);
        $this->beforeSwooleStart();
        $this->server->start();
    }

    public function onSwooleWorkerStart($serv, $workerId)
    {
        parent::onSwooleWorkerStart($serv, $workerId);
        $this->templateEngine = new Engine();
        $this->templateEngine->addFolder('server', __DIR__ . '/Views');
        $this->templateEngine->addFolder('app', __DIR__ . '/../app/Views');
    }

    /**
     * onSwooleRequest http发来请求
     * @param $request
     * @param $response
     */
    abstract public function onSwooleRequest($request, $response);
}