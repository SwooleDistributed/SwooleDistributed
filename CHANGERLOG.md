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