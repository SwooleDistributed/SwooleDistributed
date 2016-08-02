# SwooleDistributed
swoole 分布式通讯框架  
开发交流群：569037921  
更多信息及联系方式见wiki
# 安装须知
  1.php 7.0  
  2.需要使用最新版的swoole，请通过github下载编译swoole，1.8.7在php7.0下存在bug不建议使用  
  3.需要redis支持，安装redis扩展  swoole编译时选择异步redis选项  
  4.需要composer支持，安装composer，运行composer install安装依赖  
  5.如需集群自行搭建LVS  
# 运行
  1.php start_swoole_server.php start  
    启动swoole server服务器  
  2.php start_swoole_dispatch.php start  
    启动swoole dispatch服务器  
  3.单独启动swoole server不具备分布式特性，一台物理机只允许启动一个swoole server   
  4.swoole dispatch服务器可以和swoole server放在一个物理机上，一台物理机只允许启动一个swoole dispatch  
  5.可以启动多台swoole server和多台swoole dispatch搭建分布式系统（至少1台LVS,2台swoole server,1台swoole dispatch,1个redis）  
  6.单独启动swoole server可作为单机服务器。  
  7.修改config目录下配置，改为你自己的配置。  
  8.swoole server与swoole dispatch 必须在同一个网段。swoole dispatch无需配置，swoole server会自动发现  
  9.swoole server与swoole dispatch 都支持动态弹性部署，随时热插拔。swoole dispatch上线后30秒内被swoole server发现并建立连接  
  10.内置controller，model，task 3大模块  
  11.swoole server与swoole dispatch都被设计成无状态服务器，所有的信息共享都通过redis  
  12.最新版采用了异步redis进行数据存储，通过开启一个新的redis连接池进程，利用addProcess和sendMessage技术进行结果分发，优雅解决异步问题。  
  13.注意taskproxy为单例，不要变成成员变量使用，用到时load  
  14.dispatch服务器增加使用redis只读服务器的功能，提高跨服务器通讯的效率，建议将dispatch和redis安装在同一台物理机上，并做好redis的主从设置  
  15.最新版本已经拥有完整的MVC结构，增加了View模块搭配模版引擎完善http开发  
  16.同时支持TCP和HTTP方式请求服务器，公用一套路由，可自定义路由规则，通过不同端口访问服务器  
  17.完善mysql异步连接池，mysql语法构建器，增加对异步事务的支持  
# 拓扑图
  ![image](https://github.com/tmtbe/SwooleDistributed/blob/master/screenshots/topological-graph.jpg)
# 文档（待完善）
    
# 效率测试
  环境：2台i3 8G ubuntu服务器  
  A：serevr+redis（主）+dispatch  
  B：server+redis（从）+压测工具  
  结果：不跨服务器通讯 50Wqps  
        跨服务器通讯 20-25wqps  
  最优情况是server和dispatch和主redis分开部署，dispath和从redis部署在同一服务器上。压测工具单独部署。
  理论上这种部署跨服务器通讯可以达到40Wqps以上，性能强劲。
        
  

