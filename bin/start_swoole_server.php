<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-6-17
 * Time: 下午1:56
 */

require_once __DIR__ . '/../vendor/autoload.php';
$worker = new \app\AppServer();
$worker->overrideSetConfig = ['worker_num' => 1, 'task_worker_num' => 5];
Server\SwooleServer::run();