<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-14
 * Time: 下午1:58
 */

use Server\Components\CatCache\CatCacheRpcProxy;

/**
 * 是否启动CatCache
 */
$config['catCache']['enable'] = true;
//自动存盘时间
$config['catCache']['auto_save_time'] = 1000;
//落地文件夹
$config['catCache']['save_dir'] = BIN_DIR . '/cache/';
//RPC代理
$config['catCache']['rpcProxyClass'] = CatCacheRpcProxy::class;
/**
 * 最终的返回，固定写这里
 */
return $config;
