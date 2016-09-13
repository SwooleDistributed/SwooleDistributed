<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-6-17
 * Time: 下午1:56
 */

require_once __DIR__ . '/vendor/autoload.php';
$worker = new Server\SwooleDistributedServer();
$worker->overrideSetConfig = ['worker_num' => 2, 'task_worker_num' => 2];
Server\SwooleServer::run();