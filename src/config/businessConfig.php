<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-14
 * Time: 下午1:58
 */
/**
 * tcp访问时方法的前缀
 */
$config['tcp']['method_prefix'] = '';
/**
 * http访问时方法的前缀
 */
$config['http']['method_prefix'] = 'http_';

//http服务器绑定的真实的域名或者ip:port，一定要填对,否则获取不到文件的绝对路径
$config['http']['domain'] = 'http://localhost:8081';

//默认访问的页面
$config['http']['index'] = 'index.html';
return $config;