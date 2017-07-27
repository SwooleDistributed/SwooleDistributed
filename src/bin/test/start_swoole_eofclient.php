<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-6-17
 * Time: 下午1:56
 */
$worker_num = 100;
$total_num = 100000;
$GLOBALS['package_eof'] = "\r\n";
$ips = ['127.0.0.1', '192.168.8.48'];
$GLOBALS['total_num'] = $total_num;
$GLOBALS['worker_num'] = $worker_num;
$GLOBALS['count'] = 0;
$GLOBALS['count_page'] = 0;
$GLOBALS['test_count'] = 0;
for ($i = 0; $i < $total_num; $i++) {
    $GLOBALS['test'][$i] = $i;
}
function encode($buffer)
{
    return $buffer . "\r\n";
}

function json_pack($controller_name, $method_name, $data)
{
    $pdata['controller_name'] = $controller_name;
    $pdata['method_name'] = $method_name;
    $pdata['data'] = $data;
    return json_encode($pdata, JSON_UNESCAPED_UNICODE);
}

for ($i = 1; $i <= $worker_num; $i++) {
    $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC); //异步非阻塞
    $client->count = 0;
    $client->i = $i;
    $client->total_num = $total_num / $worker_num;
    $client->set(array(
        'open_eof_split' => true,
        'package_eof' => $GLOBALS['package_eof'],
        'package_max_length' => 2000000
    ));
    $client->on("connect", 'connect');

    $client->on("receive", 'receive');

    $client->on("error", function ($cli) {
        exit("error\n");
    });

    $client->on("close", function ($cli) {

    });

    $client->connect($ips[array_rand($ips)], 9092, 0.5);
}
function connect($cli)
{
    $cli->send(encode(json_pack('TestController', 'bind_uid', $cli->i)));
    swoole_timer_after(1000, function () use ($cli) {
        $GLOBALS['start_time'] = getMillisecond();
        for ($i = 0; $i < $cli->total_num; $i++) {
            $cli->send(encode(json_pack('TestController', 'efficiency_test', $GLOBALS['test_count'])));
            $GLOBALS['test_count']++;
        }
    });
}

function receive($cli, $data)
{
    $data = str_replace($GLOBALS['package_eof'], '', $data);
    $GLOBALS['count']++;
    unset($GLOBALS['test'][$data]);
    if ($GLOBALS['count'] >= ($GLOBALS['count_page'] + 1) * $GLOBALS['total_num'] / 5) {
        print_r("Left->" . count($GLOBALS['test']) . "\tGet->" . $GLOBALS['count'] . "\n");
        $GLOBALS['count_page']++;
    }

    if (count($GLOBALS['test']) == 0) {
        $total_time = getMillisecond() - $GLOBALS['start_time'];
        $qps = $GLOBALS['total_num'] / $total_time * 1000;
        print_r("qps:$qps\n");
        exit(0);
    }
}

function getMillisecond()
{
    list($s1, $s2) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($s1) + floatval($s2)) * 1000);
}
