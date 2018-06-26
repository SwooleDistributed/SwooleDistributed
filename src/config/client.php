<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-2-27
 * Time: 下午4:07
 */
$config['httpClient']['asyn_max_count'] = 10;
$config['tcpClient']['asyn_max_count'] = 10;
$config['tcpClient']['test']['pack_tool'] = 'LenJsonPack';
//默认用于consul微服务的rpc配置
$config['tcpClient']['consul']['pack_tool'] = 'LenJsonPack';
//不同服务指定不同解析策略
$config['tcpClient']['consul_MathService']['pack_tool'] = 'LenJsonPack';
return $config;