<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-10
 * Time: 下午5:58
 */

//是否启用backstage
$config['backstage']['enable'] = false;
//web页面访问端口
$config['backstage']['port'] = 18000;
//提供的ws端口
$config['backstage']['socket'] = "0.0.0.0";
$config['backstage']['websocket_port'] = 18083;
//设置路径
$config['backstage']['bin_path'] = "/bin/exec/backstage";
return $config;