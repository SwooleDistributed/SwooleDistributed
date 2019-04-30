# SwooleDistributed
Official website: http://sd.youwoxing.net
At the end of this year, it took more than two years of iteration. This is a full year of SD framework. Through continuous iteration and improvement of SD framework, it has a good reputation in the circle. Many new frameworks draw on SD design ideas, SD The framework is also used by many entrepreneurial companies and large enterprises.
## SDHelp
SDHELP is a SD-specific developer tool that enables breakpoint debugging, code coverage reporting, and more.
https://github.com/SwooleDistributed/SDHelper-Bin
 ![](https://box.kancloud.cn/e72f48d067f38325e8bce143ae03263c_965x705.png)
 ![](https://box.kancloud.cn/f1c6d430270cb6aecd669ca5c65424c5_962x672.png)
 ![](https://box.kancloud.cn/460cc43f50afe029e6dcb424d1dd3f64_963x672.png)
 ![](https://box.kancloud.cn/2bbd1490cb947b29cc566c1d07f26989_966x690.png)
 ![](https://box.kancloud.cn/aab4cb457dae925226a59a71c8a3d819_964x690.png)
## What is the SD framework?
The SD framework is called SwooleDistributed. From the name, one is Swoole and the other is Distributed. It is an application server framework that can be distributedly deployed based on Swoole extension.
With the efficient development environment of PHP, Swoole's high-performance asynchronous network communication engine, and other highly available extensions and tools, the SD framework provides developers with a stable, efficient and powerful application server framework.

## Entry cost
To be honest, compared to the current popular FPM framework, the entry cost of SD is relatively high, because the design concept is different and the operating environment is completely different from the traditional PHP-FPM environment, for the long-term use of LAMP (LANP) technology. Developers will have a period of adaptation, if the development of the application is simple and the system complexity is low, then SD is still relatively easy to get started, according to simple examples and documentation, you can start the exploration of SD almost immediately, but if The development of complex applications, then the many components of the SD still need you to get familiar with getting started.

## What are the powerful features of the SD framework?
Here we list the various functions and module components provided by SD.
* Mixed protocol
The SD framework supports long connection protocols TCP, WebSocket, Short Connection Protocol HTTP, and UDP.
    By configuring different open ports, developers can easily manage different protocols and share a set of business codes. Of course, you can isolate the code through intelligent routing.
    Long connections can be configured with different data transfer protocols, such as binary protocol text protocols. The wrapper unpacker interface provided by the framework can customize various protocol packages, and various protocols can be automatically converted. You send a message through the broadcast, which flows to different clients. Different protocols are used between the clients, then the framework automatically converts different protocol packages according to different ports.
    You can also send push messages to all long-connected clients via Http. Services like this hybrid protocol collaboration are extremely simple on the SD framework.
* MVC and intelligent routing
The design of the framework is the MVC architecture, in which each level can continue to divide the sub-level, the developer can continue to layer the Controller through different folders for management, or the Model can be subdivided into business layer and data layer, which Look at the developer's own system design. The intelligent route will process the unpacked data of the unpacker and is responsible for passing this data to the Controller layer.
* Middleware
The SD framework also provides middleware that can process incoming data, such as cleaning up anomalous data, modifying data, traffic statistics, and collecting logs. The middleware can be set up multiple and they are bound to the port.
* Object pool
Most objects in the SD framework use the object pool technology. The object pool technology is beneficial to the stability of the system memory, reducing the number of GCs, and improving the operating efficiency of the system. It turns out that the object pool has greatly contributed to the stability of the system. Developers can also use this set of object pool technology to increase the reuse of objects, reduce the frequency of GC and NEW, and have great stability improvements in system glitch and memory leaks.
* Asynchronous client and connection pool
Mysql, Redis, Http client, Tcp client, and other more complex clients are asynchronous in the SD framework. Asynchronously solves the overall concurrency of the system, but the asynchronous client needs to provide connection pool maintenance. The SD framework provides a connection pool. Developers do not need to manage the connection pool themselves, just use them.
* Coroutine
Asynchronous event callbacks solve for concurrent performance, but cause confusion in business code. The SD framework provides a coroutine to solve this problem. The synchronous keyword is used to provide synchronous asynchronous writing, which eliminates a lot of callback nesting in business writing. You can achieve asynchronous performance through yield+synchronous writing.
    Coroutine provides a complete set of systems, including timeouts, exceptions, hibernation, multiple selections, and the creation of user coroutines.
* Scheduled tasks
As the name suggests, the tasks are executed regularly.
* Task delivery
Support for delivering time-consuming tasks to the Task process.
* Automatic Reload
The framework's automatic Reload function can be turned on so that code changes are immediately responded.
    
**The above are all some basic functions that are often used when developing applications. Then the following are some advanced features. **

* Cluster and microservices
The framework provides cluster deployment. By enabling the cluster switch and deploying the Consul tool server, we can start the cluster tour. The message function in the framework supports the cluster environment. By exposing the API and listening to the API, we can implement microservices. In microservices, we provide health monitoring, fusing, timeout, load balancing, request migration and more.
    The cluster uses a peer-to-peer network, there is no intermediate node, and there is no single point of hidden danger. The design concept is shown in the following figure.
    ![image](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/k3.png)

* Subscription and release
The subscription publishing feature provided by SD also supports the cluster environment, and it strictly follows the subscription publishing specification defined by MQTT and implements all the functions. This is probably the best and best subscription publishing feature.
* Event distribution
The cross-process cross-server event dispatching feature, the infrastructure of many SD frameworks is based on this build.
* User process management and interprocess RPC
The SD framework re-encapsulates user processes. Developers can start their own user processes. User processes can be asynchronous or synchronous. They also support various connection pools and coroutines. User processes are useful. The same framework also supports users. RPC calls to each other between the process and the worker process.
* Timed tasks under the cluster
Through the Consul, you can set the scheduled task, and it will be synchronized to all the servers in the cluster to execute. The cluster server will elect a leader, and you can determine whether the task is executed by obtaining whether it is a leader.
* Context context
This is the context shared in the entire process of message processing, very practical and convenient.
    
**The next step is the SD feature component**
* Asynchronous AMQP client and distributed task system
Message Queuing Protocol AMQP, the framework provides an asynchronous client that supports the AMQP protocol. It can be linked with RabbitMQ to build a distributed task system through distributed task components provided by the framework.
* Asynchronous MQTT client
Asynchronous MQTT client can subscribe and publish with MQTT service
* MQTT Simple Cluster Server
Supports simple MQTT server of QOS0 level and supports cluster deployment.
* Server monitoring system
Provides a server monitoring background, which can monitor the cluster and monitor the specific running status of a server.
    Here are some screenshots
    ![](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/screenshot_1511494439977.png)
    ![](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/screenshot_1511494520746.png)
    ![](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/screenshot_1511494552885.png)
    ![](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/screenshot_1511494572162.png)
    ![](https://raw.githubusercontent.com/tmtbe/SwooleDistributed/v2/screenshots/screenshot_1511494591862.png)
## SD framework is far more than now

The SD framework has been developing at a high speed, and more developers will have a better future.
Documentation with the SD framework and official website
[Official website] (http://sd.youwoxing.net/)
[Documentation] (http://docs.youwoxing.net/)
[GitHub](https://github.com/tmtbe/SwooleDistributed)
If you like, please support with a star~


High performance, high concurrency, PHP asynchronous distributed framework,power by ext-swoole

Development communication QQ-group:569037921

Simple websocket case

Chat room: https://github.com/tmtbe/SD-todpole

Live Demo: http://114.55.253.83:8081/

The official website: http://sd.youwoxing.net

Development document: http://docs.youwoxing.net

Instructional video: http://v.qq.com/boke/gplay/337c9b150064b5e5bcfe344f11a106c5_m0i000801b66cfv.html

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
Php vendor/tmtbe/swooledistributed/src/Install.php
```
The server can be executed in the bin at the end of the installation.

## Advantage

High performance and high concurrency, asynchronous event driven

2.HttpClient, client, Mysql, Redis connection pooling

3.Timed task system

4.Coroutine Support

5.Using object pooling mode, optimizing memory allocation
