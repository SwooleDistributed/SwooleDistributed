# SwooleDistributed v2.4.1 更新

swoole 分布式通讯框架  
开发交流群：569037921  

简单websocket案例 
聊天室 https://github.com/tmtbe/SD-todpole

官网：http://sd.youwoxing.net

文档：http://docs.youwoxing.net

视频：http://v.qq.com/boke/gplay/337c9b150064b5e5bcfe344f11a106c5_m0i000801b66cfv.html

可以通过composer安装

autoload必须要指定app和test。
```
{
  "require": {
    "tmtbe/swooledistributed":">2.0.0"
  },
 "autoload": {
    "psr-4": {
      "app\\": "src/app",
      "test\\": "src/test"
    }
  }
}
```
然后在根目录（vendor上级目录）执行下面代码
```
php vendor/tmtbe/swooledistributed/src/Install.php
```
安装结束可以在bin中执行服务器。

微服务框架SD2.4.1

1.协程优化，速度更快，功能更强大

2.httpClient，client连接池，REST和RPC的支持

3.timerTask优化

4.协程熔断器，可以超时降级和熔断恢复

5.包结构调整优化，分离协程，连接池模块，模块解耦

6.全链路监控，开放Context上下文

7.推荐使用对象池模式，优化内存分配和GC

8.提供分布式锁功能，简单易用，更多分布式工具逐步更新

9.众多异步客户端，MQTT，AMQP等

10.通过consul实现注册中心

11.分布式部署

12.用户进程管理
