<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-2-27
 * Time: 下午4:07
 */
$config['httpClient']['asyn_max_count'] = 10;
$config['tcpClient']['asyn_max_count'] = 10;
$config['tcpClient']['test']['set'] = [
    'open_length_check' => 1,
    'package_length_type' => 'N',
    'package_length_offset' => 0,       //第N个字节是包长度的值
    'package_body_offset' => 0,       //第几个字节开始计算长度
    'package_max_length' => 2000000,  //协议最大长度
];
$config['tcpClient']['test']['pack_tool'] = 'JsonPack';

//默认用于consul微服务的rpc配置
$config['tcpClient']['consul']['set'] = [
    'open_length_check' => 1,
    'package_length_type' => 'N',
    'package_length_offset' => 0,       //第N个字节是包长度的值
    'package_body_offset' => 0,       //第几个字节开始计算长度
    'package_max_length' => 2000000,  //协议最大长度
];
$config['tcpClient']['consul']['pack_tool'] = 'JsonPack';
//不同服务指定不同解析策略
$config['tcpClient']['consul_MathService']['set'] = [
    'open_length_check' => 1,
    'package_length_type' => 'N',
    'package_length_offset' => 0,       //第N个字节是包长度的值
    'package_body_offset' => 0,       //第几个字节开始计算长度
    'package_max_length' => 2000000,  //协议最大长度
];
$config['tcpClient']['consul_MathService']['pack_tool'] = 'JsonPack';
return $config;