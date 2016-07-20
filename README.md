# SwooleDistributed
swoole 分布式通讯框架
# 安装须知
  1.php 7.0  
  2.需要使用最新版的swoole，请通过github下载编译swoole，1.8.7在php7.0下存在bug不建议使用  
  3.需要redis支持，安装redis扩展  
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
# 拓扑图
  ![image](https://github.com/tmtbe/SwooleDistributed/blob/master/screenshots/topological-graph.jpg)
# 文档（待完善）
    


