<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-10
 * Time: 下午5:58
 */
//是否启用consul
$config['consul_enable'] = false;
//服务器名称，同种服务应该设置同样的名称，用于leader选举
$config['consul']['leader_service_name'] = 'Test';
//node的名字，每一个都必须不一样
$config['consul']['node_name'] = 'SD-1';
//consul的data_dir默认放在临时文件下
$config['consul']['data_dir'] = "/tmp/consul";
//consul join地址，可以是集群的任何一个，或者多个
$config['consul']['start_join'] = ["192.168.9.38"];
//本地网卡地址
$config['consul']['bind_addr'] = "192.168.8.47";
//监控服务
$config['consul']['watches'] = ['MathService'];
//发布服务
//$config['consul']['services']=['MathService'];
return $config;