# SwooleDistributed
官网：http://sd.youwoxing.net
今年年底历时2年多的迭代，这是SD框架硕果满满的一年，通过不断的迭代和改进SD框架已经在圈内有良好的口碑，不少新生的框架借鉴了SD的设计思想，SD框架也被不少创业型公司和大型企业使用。
## SDHelp
SDHELP是SD专属的开发者工具，可以实现断点调试，代码覆盖率报告等功能。
https://github.com/SwooleDistributed/SDHelper-Bin
 ![](https://box.kancloud.cn/e72f48d067f38325e8bce143ae03263c_965x705.png)
 ![](https://box.kancloud.cn/f1c6d430270cb6aecd669ca5c65424c5_962x672.png)
 ![](https://box.kancloud.cn/460cc43f50afe029e6dcb424d1dd3f64_963x672.png)
 ![](https://box.kancloud.cn/2bbd1490cb947b29cc566c1d07f26989_966x690.png)
 ![](https://box.kancloud.cn/aab4cb457dae925226a59a71c8a3d819_964x690.png)
## SD框架到底是什么技术
SD框架全称SwooleDistributed，从名称上看一个是Swoole一个是Distributed，他是基于Swoole扩展的可以分布式部署的应用服务器框架。
借助于PHP的高效开发环境，Swoole的高性能异步网络通信引擎，以及其他的高可用的扩展和工具，SD框架提供给广大开发者一个稳定的高效的而且功能强大的应用服务器框架。

## 入门成本
老实的说相对比目前热门的FPM框架来说，SD的入门成本相对还是比较高的，因为设计理念不同以及和传统PHP-FPM环境完全不同的运行环境，对于长时间使用LAMP（LANP）技术的开发人员来说会有一段时间的适应期，如果开发应用简单涉及到的系统复杂度低，那么SD上手还是比较容易，根据简单的例子和文档几乎立即就能开启SD的探索之旅，但是如果开发的是复杂的应用那么SD包含的众多组件还是需要你慢慢熟悉上手的。

## SD框架到底包含哪些强大的功能呢
我们这里列举下SD提供的各种各样的功能以及模块组件
* 混合协议
	SD框架支持长连接协议TCP，WebSocket，短连接协议HTTP，以及UDP。
    通过配置开放不同的端口开发者可以轻松管理不同的协议，并且可以共用一套业务代码，当然你可以通过智能路由进行代码的隔离。
    长连接可以配置不同的数据传输协议，比如二进制协议文本协议等等，通过框架提供的封装器解包器接口可以自定义各种各种的协议封装，并且各种协议之间可以自动转换，比如你通过广播发送一个信息，该信息流向不同客户端，客户端间采用不同协议，那么框架会根据不同的端口自动转换不同的协议封装。
    你也可以通过Http给所有长连接客户端发送推送消息，类似这种混合协议协作的业务在SD框架上会异常简单。
* MVC以及智能路由
	框架的设计是MVC架构，其中每一个层级都可以继续划分子层级，开发者可以将Controller继续分层通过不同文件夹进行管理，也可以将Model进行细分，划分为业务层和数据层，这都看开发者自身的系统设计。智能路由将处理解包器解包后的数据，负责将这些数据传递到Controller层。
* 中间件
	SD框架还提供了中间件，中间件可以对流入的数据进行处理，比如清理异常数据，修改数据，流量统计，搜集日志等功能。中间件可以设置多个，他们和端口进行绑定。
* 对象池
	SD框架内大多数的对象都使用了对象池技术，对象池技术有利于系统内存的稳定，减少GC的次数，提高系统的运行效率，事实证明对象池对系统稳定做出了极大的贡献，开发者也可以使用这一套对象池技术，增加对对象的复用，减少GC和NEW的频率，对系统毛刺现象和内存泄露方面都有很大的稳定性提升。
* 异步客户端以及连接池
	Mysql，Redis，Http客户端，Tcp客户端，等等其他更为复杂的客户端，在SD框架中均为异步的模式，异步解决了系统整体的并发能力，但异步客户端需要提供连接池维持，SD框架提供了连接池，开发者不需要自己管理连接池，只需要使用即可。
* 协程
	异步事件回调解决的是并发性能，但造成的是业务代码的混乱。SD框架提供了协程解决了这一问题，通过yield关键字提供对异步的同步写法，消除了业务书写上的大量回调嵌套，你可以通过yield+同步的写法实现异步的性能。
    协程提供了一整套完整的体系，包括超时，异常，休眠，多路选择，以及创建用户协程等等功能。
* 定时任务
	顾名思义定时执行的任务。  
* 任务投递
	支持将耗时任务投递到Task进程。
* 自动Reload
	可以开启框架的自动Reload功能，这样代码修改会被立即响应。
    
**上面描述的都是一些基础功能，大家开发应用时经常用到的，那么下面则是一些高级功能。**

* 集群以及微服务
	框架提供集群部署，通过开启集群开关，部署Consul工具服务器，我们就可以开启集群之旅，框架中消息功能都是支持集群环境的。通过暴露API，监听API，我们可以实现微服务，微服务中我们又提供了健康监控，熔断，超时，负载均衡，请求迁移等等功能。
    集群采用的是对等网络，没有中间节点，没有单点隐患，设计理念如下图所示。
    ![image](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/k3.png)

* 订阅与发布
	SD提供的订阅发布功能也是支持集群环境，并且它严格的按照MQTT所定义的订阅发布规范，并且实现了所有的功能。这恐怕是最好最优秀的订阅发布功能了。
* 事件派发
	跨进程跨服务器的事件派发功能，很多SD框架的基础设施都是基于这个搭建的。
* 用户进程管理以及进程间RPC
	SD框架重新封装了用户进程，开发者可以启动自己的用户进程，用户进程可以是异步的也可以是同步的，也是支持各种连接池和协程，用户进程的用处很多，同样框架也支持用户进程和Worker进程间互相RPC调用。
* 集群下的定时任务
	通过Consul可以设置定时任务，并且会同步到集群所有的服务器上去执行，集群服务器会选举出一个Leader，可以通过获取是否是Leader来决定这个任务是否被执行。
* Context上下文
	这个是在消息处理整个流程中被共享的上下文，很实用，很方便。
    
**接下来介绍的是SD特色组件**
* 异步AMQP客户端以及分布式任务系统
	消息队列协议AMQP，框架提供了一个支持AMQP协议的异步客户端，可以和RabbitMQ联动，通过框架提供的分布式任务组件，可以搭建分布式任务系统。
* 异步MQTT客户端
	异步的MQTT客户端可以和MQTT服务实现订阅与发布
* MQTT简易集群服务器
	支持QOS0级别的简易MQTT服务器，支持集群部署。
* 服务器监控系统
	提供了一个服务器监控后台，可以对集群进行监控，也可以监控某一台服务器的具体运行状况。
    下面是一些截图
    ![](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/screenshot_1511494439977.png)
    ![](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/screenshot_1511494520746.png)
    ![](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/screenshot_1511494552885.png)
    ![](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/screenshot_1511494572162.png)
    ![](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/screenshot_1511494591862.png)
## SD框架远远不止现在

SD框架一直在高速发展中，有更多开发者的参与才会有更好的未来。
附带SD框架的文档以及官网
[官网](http://sd.youwoxing.net/)
[文档](http://docs.youwoxing.net/)
[GitHub](https://github.com/tmtbe/SwooleDistributed)
如果你喜欢，请打个星星支持下～


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
