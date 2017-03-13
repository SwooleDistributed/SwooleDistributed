<?php
/**
 * consul会调用，并通知服务器
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-9
 * Time: 上午10:42
 */
require_once __DIR__ . '/../vendor/autoload.php';
global $argv;
$serviceName = trim($argv[1]);
while ($line = fopen('php://stdin', 'r')) {
    $input = fgets($line);
    break;
}
$input = $serviceName . "@" . $input;
$config = new \Noodlehaus\Config(__DIR__ . "/../src/config");
$cli = new swoole_http_client('127.0.0.1', $config['http_server']['port']);
$cli->setData($input);
$cli->execute('/ConsulController/ServiceChange', function ($cli) {
    print_r('ok');
    exit(0);
});