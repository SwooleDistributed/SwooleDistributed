# SwooleDistributed

TCP/Websocket/Http全栈框架

高可用，高性能，高并发，天然分布式集群架构。

主要特性：

1.多端口，多协议，多服务，端口协议间自动转换，高度可配置。

2.MVC架构，MC支持多层分级。

3.高效协程可达95%异步效率，协程模块设施完善，错误代码可跟踪，异常处理可捕获，稳定性一流。

4.异步连接池管理，支持多种异步客户端，mysql，redis，mqtt，amqp，http，tcp。

5.支持consul微服务配置管理，集群自动发现，Leader选举，微服务自动负载，节点与服务健康监控管理。

6.支持搭建简易MQTT集群服务器，MQTT集群订阅树。

7.用户进程管理，定时任务管理。

8.对象池复用技术，减少GC，降低内存波动，提高整体性能和系统稳定性，杜绝内存泄露。

9.支持搭建AMQP异步任务作业系统。

10.各种服务器工具组件，满足各类开发需求。

11.支持Docker部署，Docker-Compose资源编排，DEV开发环境搭建教程。

12.维护频繁，文档丰富，swoole老牌框架。

文档地址：http://docs.youwoxing.net

High performance, high concurrency, PHP asynchronous distributed framework,power by ext-swoole

Development communication QQ-group：569037921  

Simple websocket case

Chat room: https://github.com/tmtbe/SD-todpole

Live Demo: http://114.55.253.83:8081/

The official website：http://sd.youwoxing.net

Development document：http://docs.youwoxing.net

Instructional video：http://v.qq.com/boke/gplay/337c9b150064b5e5bcfe344f11a106c5_m0i000801b66cfv.html

## Install
You can install via composer

Autoload must specify `app` and `test`.
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
Then execute the following code in the root directory (the vendor higher directory)
```
php vendor/tmtbe/swooledistributed/src/Install.php
```
The server can be executed in the bin at the end of the installation.

## Advantage

1.High performance and high concurrency, asynchronous event driven

2.HttpClient, client, Mysql, Redis connection pooling

3.Timed task system

4.Coroutine Support

5.Using object pooling mode, optimizing memory allocation and GC

6.Many asynchronous clients, such as MQTT, AMQP, etc.

7.Support cluster deployment

8.User process management

9.Support multi port, multi protocol, automatic conversion between protocols

10.Micro service management based on Consul

11.Automatic discovery of cluster nodes based on Consul

12.Support pubish-subscribe mode

13.MQTT Server

14.Asynchronous operating system

## Architecture diagram

### Class inheritance structure
 ![image](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/k1.png)

### Process structure
 ![image](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/k2.png)
 
### Cluster structure
 ![image](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/k3.png)
## Donation
If you like the project, I hope you donate this project so that the project will get better development, 
Thank you.

Alipay：

 ![image](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/pay.png)
