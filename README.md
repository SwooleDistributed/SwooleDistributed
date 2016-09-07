# SwooleDistributed v1.2
swoole 分布式通讯框架  
开发交流群：569037921  

# 文档
  https://tmtbe.gitbooks.io/swooledistributed/content/

# 特性  
1. 优秀的框架（MVC）设计,丰富的支持极大加快开发速度  
2. 通过开启不同端口同时支持TCP和HTTP，同一逻辑处理不同协议  
3. 全异步支持，无需手动处理连接池，异步redis,异步mysql，mysql语法构建器，支持异步mysql事务,异步httpclient，效率出众  
4. 协程模式全支持，异步redis，异步mysql，异步httpclient，异步task，全部都提供了协程模式，业务代码摆脱处处回调的困扰（不是swoole2.0，php7同样支持）  
5. 支持协程嵌套，支持协程内异常处理（和正常逻辑用法一样）  
6. 额外提供了protobuf完整RPC实例，轻松使用protobuf  
7. 天然分布式的支持，一台机器不够零配置，零代码修改完成高效分布式系统的搭建  
8. 完善详细的文档，还有实例代码，轻松掌握  
9. 线上项目打造维护，不断优化与改进  

# 安装须知
1. php 7.0  5.6的用户需要自己修改源码，将php7的部分语法重写。强烈推荐php7.X搭配最新的swoole1.X系列。
2. 强烈建议使用最新版的swoole，请通过github下载编译。最新版修复了很多php7下的bug  
3. 需要redis支持，安装hredis扩展  swoole编译时选择异步redis选项  
4. 需要composer支持，安装composer，运行composer install安装依赖  
5. 如需集群自行搭建LVS  

# 运行
1. php start_swoole_server.php start  

    启动swoole server服务器  
2. php start_swoole_dispatch.php start  

    启动swoole dispatch服务器  
3. 单独启动swoole server不具备分布式特性，一台物理机只允许启动一个swoole server   
4. swoole dispatch服务器可以和swoole server放在一个物理机上，一台物理机只允许启动一个swoole dispatch  
5. 可以启动多台swoole server和多台swoole dispatch搭建分布式系统（至少1台LVS,2台swoole server,1台swoole dispatch,1个redis）  
6. 单独启动swoole server可作为单机服务器。  
7. 修改config目录下配置，改为你自己的配置。  
8. swoole server与swoole dispatch 必须在同一个网段。swoole dispatch无需配置，swoole server会自动发现  
9. swoole server与swoole dispatch 都支持动态弹性部署，随时热插拔。swoole dispatch上线后30秒内被swoole server发现并建立连接  

# 拓扑图
  ![image](https://github.com/tmtbe/SwooleDistributed/blob/master/screenshots/topological-graph.jpg)
​    
# 效率测试
  环境：2台i3 8G ubuntu服务器  
  A：serevr+redis（主）+dispatch  
  B：server+redis（从）+压测工具  
  结果：不跨服务器通讯 50Wqps  
        跨服务器通讯 20-25wqps  
  最优情况是server和dispatch和主redis分开部署，dispath和从redis部署在同一服务器上。压测工具单独部署。
  理论上这种部署跨服务器通讯可以达到40Wqps以上，性能强劲。
​        
# 部署说明
1. 单机模式  

这种模式只需要开启一个swoole_distributed_server即可  

2. 2-10台机器的集群模式

首先保证所有的机器都处于同一个内网网段  
配置好LVS和keeplived用于服务器组的负载均衡，dispatch服务器和从redis安装到同一个物理机上之间使用unixsock进行通讯，server服务单独部署在一台物理机上，主redis单独部署在一台物理机上，一般5台以下的server只需要搭配一个dispatch，5台以上可以搭配2个dispatch，2个dispatch服务器才有必要做redis的主从。注：dispatch服务器只会读redis完全不会写入redis。

3. 10台以上的集群模式  

这种可能性能的瓶颈主要堆积到redis的读上了，主从读写分离这种模式只能一定成程度上提高效率，出现redis瓶颈就需要进行redis集群的搭建了。  


  建议dispatch服务器要比server服务先启动，否则server寻找dispatch服务器会有30秒的延迟。  

