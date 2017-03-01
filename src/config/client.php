<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-2-27
 * Time: 下午4:07
 */
$config['httpClient']['asyn_max_count'] = 10;
$config['tcpClient']['asyn_max_count'] = 10;
$config['tcpClient']['set'] = [
    'open_length_check' => 1,
    'package_length_type' => 'N',
    'package_length_offset' => 0,       //第N个字节是包长度的值
    'package_body_offset' => 0,       //第几个字节开始计算长度
    'package_max_length' => 2000000,  //协议最大长度
];
$config['tcpClient']['pack_tool'] = 'JsonPack';
return $config;