<?php
/**
 * consul会调用，并通知服务器
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-9
 * Time: 上午10:42
 */

use Server\CoreBase\PortManager;

require_once 'define.php';
global $argv;
$serviceName = trim($argv[1]);
while($line = fopen('php://stdin','r')){
    $input = fgets($line);
    break;
}
$input = $serviceName."@".$input;
$config = new \Noodlehaus\Config(__DIR__ . "/../src/config");
$cli = new swoole_http_client('127.0.0.1', getHttpPort($config));
$cli->setData($input);
$cli->execute('/ConsulController/ServiceChange',function ($cli)
{
    print_r('ok');
    exit(0);
});

function getHttpPort($config)
{
    $ports = $config->get('ports');
    foreach ($ports as $value) {
        if ($value['socket_type'] == PortManager::SOCK_HTTP) {
            return $value['socket_port'];
        }
    }
    throw new Exception('没有找到http端口');
}