<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-10
 * Time: 下午5:58
 */
//是否启用consul
$config['consul']['enable'] = false;
//数据中心配置
$config['consul']['datacenter'] = 'dc1';
//开放给本地
$config['consul']['client_addr'] = '127.0.0.1';
//服务器名称，同种服务应该设置同样的名称，用于leader选举
$config['consul']['leader_service_name'] = 'Test';
//node的名字，每一个都必须不一样,也可以为空自动填充主机名
$config['consul']['node_name'] = 'SD-1';
//consul的data_dir默认放在临时文件下
$config['consul']['data_dir'] = "/tmp/consul";
//consul join地址，可以是集群的任何一个，或者多个
$config['consul']['start_join'] = ["192.168.8.85"];
//本地网卡设备
$config['consul']['bind_net_dev'] = "eth0";
//监控服务
$config['consul']['watches'] = ['MathService', 'TestController'];
//发布服务
$config['consul']['services'] = ['MathService:9091', 'MathService:8081', 'TestController:9091', 'TestController:8081'];
//是否开启TCP集群,启动consul才有用
$config['cluster']['enable'] = true;
//TCP集群端口
$config['cluster']['port'] = 9999;
return $config;