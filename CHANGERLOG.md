# CHANGELOG
## 2.4.6
1. 修改了绝大多数由于集群重构导致的API报错问题
2. 移除了集群SESSION（设计存在问题，有待优化，暂时移除）
3. 增加了集群订阅/发布功能

## 2.4.7
1. 修改了IPack，IRoute的errorHandle($e, $fd)接口，将异常传入进去
2. 支持MQTT规则的订阅树
3. 现在访问到被保护或者私有的控制器方法不会报错，会直接转到defaultMethod中去
4. 修复了NonJsonPack的一个缓存bug

## 2.4.8
1. 修复Miner中Join别名问题
2. Consul.php增加datacenter配置，可以设置数据中心名称，默认为dc1
3. 修复HttpServer中静态默认页面存在BUG
4. fileHeader增加xhtml类型的支持
5. 一些轻微问题的修复

## 2.4.9
1.移除了些依赖，主要是清除了框架对protobuf的依赖，需要的可以自己在项目的composer中加上，同样也移除了默认生成的代码，和运行命令。
```json
"protobuf-php/protobuf": "~0.1.2",
"protobuf-php/protobuf-plugin": "^0.1.2"
```
2.移除了ds扩展的强制依赖，没有ds扩展也能正常运行，可以通过下面命令安装
```
sudo pecl install ds 
```
3.bussiness.php中新增了全局关闭gzip的配置，可以通过设置gzip_off为true，关闭全局http_gzip。即使在输出时设置gzip为true也无法进行gzip压缩。
```
$config['http']['gzip_off'] = false;
```
4.server.php配置中dispatch_mode默认设置改为了2

5.server.php配置中新增了max_connection，最大连接数越大消耗的内存越大，这个数字和代码中uid_fd_table有密切联系,不像以前为65536

6.一些内部命名的修改

7.IPack接口轻微修改pack方法增加了一个名为topic的参数，用于订阅发布时的识别

8.单元测试中的多参数时解析bug修复

9.订阅发布的规则修改为和MQTT的规则一致

10.存在一个效率问题的修复，qps约提升了30%

11.MQTT服务器（暂未提供源码）

## 2.4.10
1.Mysql,Redis协程性能调优，现在协程的性能可以达到异步回调的95%

2.Loader修改，现在可以使用class名称进行loader

```php
$this->loader->model(TestModel::class, $this);
```

3.Model支持分级，可以在Models目录下新建新的子目录。

4.提供了sleepCoroutine命令，可以通过这个代替sleep,改方法不会堵塞进程
```
yield sleepCoroutine(1000);
```
## 2.4.11
1.Controller以及Model的initialization均已支持协程，通过在initialization中抛出异常会立即中断后面所有的函数执行。

2.Mysql构建器会在某种特殊情况下导致请求间出现sql语句扰乱的bug，现在已经修复。

3.创建新的Mysql连接池在Controller以及Model通过get_instance()->getAsynPool()使用时需要在initialization方法中通过installMysqlPool命令进行安装
```php
$this->installMysqlPool($this->mysql_pool);
```

4.修复了一个RedisRoute的bug，该bug导致无参数的redis请求会报错。

5.修复了协程超时报错使用try捕获时控制台依旧会打印报错信息的问题。

6.PortManager可以在Ports.php配置中设置端口专用的回调地址。

## 2.4.12
1.Mysql（task）同步模式也可以使用dump打印信息

2.Controller可以添加文件夹，修改了默认NormalRoute支持多级访问
```
http://localhost:8081/V1/AppController/test
```
比如V1就是一个文件夹，默认访问的是app\V1\AppController::test();


## 2.4.13
1.EventDispatcher支持集群发布消息

2.ProcessManager针对同类型进程进行标识

3.HttpInput getAllPostGet接口行为变更

4.docker化部署支持性改良

# 2.5.0
1.已不再依赖pid文件，通过新的方式识别SD进程，server.php新增name字段，不同name代表不同服务器，
一台机器不允许启动多个相同name的服务器

2.config支持文件夹区分，通过设置SD_CONFIG_DIR环境变量来识别文件夹

3.consul.php配置文件变更，新增client_addr字段默认为127.0.0.1只开放本地访问，node_name字段现在可以不填，
如果不填写则使用机器名，可通过SD_NODE_NAME环境变量来设置node_name。新增bind_net_dev字段，移除bind_addr字段，
默认为eth0网卡名。

4.对docker友好，提供SD运行环境的docker镜像registry.cn-hangzhou.aliyuncs.com/youwoxing/swoole

5.提供docker编排模板及其集群环境搭建实例https://github.com/tmtbe/swoole-docker

# 2.5.1
1.AMQP异步任务处理系统

2.MQTT简易服务器