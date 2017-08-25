# SwooleDistributed

High performance, high concurrency, PHP asynchronous distributed framework,power by ext-swoole

Development communication QQ-group：569037921  

Simple websocket case

Chat room https://github.com/tmtbe/SD-todpole

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
