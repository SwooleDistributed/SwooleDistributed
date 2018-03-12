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

# 2.5.2
1.Mysql支持RAW模式
```apple js
 $selectMiner = $this->mysql_pool->dbQueryBuilder->select('*')->from('account');
 $selectMiner = $selectMiner->where('', '(status = 1 and dec in ("ss", "cc")) or name = "kk"', Miner::LOGICAL_RAW);
```
2.修复onOpenServiceInitialization中不能使用mysql的bug

# 2.5.3
SD框架正式支持SSL。
通过Ports.php配置文件配置HTTPS，WSS。
```
$config['ports'][] = [
    'socket_type' => PortManager::SOCK_HTTP
    'socket_name' => '0.0.0.0',
    'socket_port' => 8081,
    'pack_tool' => 'LenJsonPack',
    'route_tool' => 'NormalRoute',
    'socket_ssl' => true,
    'ssl_cert_file' => $ssl_dir . '/ssl.crt',
    'ssl_key_file' => $ssl_dir . '/ssl.key',
];
```
# 2.5.4
设计问题废除了AppServer中的onUidCloseClear方法。

增加了getCloseControllerName与getCloseMethodName方法。

```
    /**
     * @return string
     */
    public function getCloseControllerName()
    {
        return 'AppController';
    }

    /**
     * @return string
     */
    public function getCloseMethodName()
    {
        return 'onClose';
    }
```
通过设置AppServer中的控制器与方法名可以将连接断开的消息转发到对应的控制器方法中。

如上，如果连接断开，会转到AppController中的onClose方法，这里不需要填写方法前缀。

# 2.5.5
1.getCloseControllerName改名为getEventControllerName

2.添加getConnectMethodName

# 2.6.0-beta
这是一个测试版本，增加了中间件，和深度优化了协程调度。
1.ports.php中添加了middlewares字段可以自定义中间件模块

2.修复了process中使用协程的问题


# 2.6.1-beta
1.Process中start方法改为了虚函数，不需要被继承了，start方法中也可以使用协程。

2.AppServer开启debug模式可以看到调用链

3.报错会打印调用链的运行状态

4.增加了基础的AOP模式

# 2.6.1
正式版本，更新此版本需要重新设置配置文件，主要在于ports.php配置需要添加中间件。

1.AppServer开启debug模式可以看到请求调用链,贯穿请求过程中的强大Context
http://docs.youwoxing.net/425321

2.AOP的支持

3.Controller和Model开放__construct，可以设置特殊AOP代理

4.协程调度器优化

5.添加中间件处理模块
http://docs.youwoxing.net/425118

6.默认添加了上海时区

7.server.php中增加了allow_ServerController，设置为false时将不能访问Server包下的Controller，建议线上填写false

8.fix ws多端口报错bug

9.fix 循环loader引发的死循环问题

10.fix 用户进程调用mysql，redis的错误问题

11.fix 细微bug

# 2.6.2
1.修复AMQPTTASK bug

2.修复MQTT Client bug

3.修复CONSUL 配置 bug

# 2.6.3
1.修复close，connect回调无法执行的bug

2.默认使用swoole的websocket握手规则，如需打开自定义握手在AppServer构造函数中添加setCustomHandshake(true)

# 2.6.4
1.修复不开启Mysql时的报错问题

2.修复websocket端口不能兼容使用http中间件的问题

3.修复了GrayLog日志插件配置上的bug

4.增加了coroutineGetAllUids方法，可以获取到所有在线的uid，支持集群

# 2.6.5
1.Server下的例子均移到App下了

2.ports.php配置增加了method_prefix，event_controller_name，close_method_name，connect_method_name，bussiness.php配置去除了相关配置，详情见http://docs.youwoxing.net/399763

3.优化服务器信息打印

4.去除了AppServer中的setDebugMode函数，debug模式改为命令行
```
php start_swoole_server.php start -de（或者-debug）
```

5.命令行debug模式增加了过滤参数--f,比如下面将只显示包含"[ip] => 127.0.0.1"的信息，可以接多个参数，参数间是或的逻辑关系。
```
php start_swoole_server.php start -de --f "[ip] => 127.0.0.1"
```

6.协程task现在可以捕获到task抛出的异常了

7.同步模式Task出错会有详细的报错

8.主题订阅树支持$SYS标识

# 2.7.0-beta

请注意这是一个测试版本，包含了一些前瞻性的功能，虽然经过了初步的测试，但仍然有可能会导致系统BUG的出现

1.“$SYS”服务器监控专用订阅主题，开发者可以订阅$SYS主题获得服务器监控信息

2.服务器间的RPC由单向通知改为双向交互

3.增加Timer定时器，该定时器在多进程中共享，A进程创建了定时B进程可以取消定时，可以在Controller，Model中使用，但请注意有严格使用方式
的规范，使用不当容易导致数据错乱。

4.UID现在不限制为int，可以使用String。

5.Controller的onExceptionHandle方法参数类型由Exception改为了Throwable

6.协程逻辑进一步得到了优化

7.一些细节方面的检修

# 2.7.0
正式版本

1.“$SYS”服务器监控专用订阅主题，开发者可以订阅$SYS主题获得服务器监控信息 

2.服务器间的RPC由单向通知改为双向交互

3.UID现在不限制为int，可以使用String。

4.Controller的onExceptionHandle方法参数类型由Exception改为了Throwable

5.协程逻辑进一步得到了优化

6.增加Timer定时器，该定时器在多进程中共享

7.各进程间，用户进程和worker进程间均可以进行RPC通讯

# 2.7.1
1.修复websocket进行reload的时候会丢失request信息的问题

2.修复了setDebug导致报错的问题

3.修复了Task中抛出异常有机会导致报错的问题

4.修复了监控服务器运行时间统计错误的问题

# 2.7.2
1.修复inotify在虚拟机不工作的问题

2.修复了task的一个内存泄露的隐患

3.task无论是否有返回始终都会有回调

# 2.7.3
1.修复Cache存在的bug

2.后台监控整理（VIP）

热烈庆祝群主猫咖店开张～留个纪念，来深圳撸猫啊

# 2.7.3.1
1.修复Cache存在的bug

2.后台监控整理（VIP）

热烈庆祝群主猫咖店开张～留个纪念，来深圳撸猫啊

# 2.7.3.3
1.backstage可以设置bin_path

2.Install可以新增文件

# 2.7.4
1.增加CatCache，仿Redis可落地高速缓存，可以在某些情况下代替Redis，访问QPS比Redis高。可以配置catCache.php,设置自动落地表的时间和位置。
可以通过设置CatCache的RPC代理，实现自己的缓存方法调用。

2.完善Process进程管理

3.修复一些bug

# 2.7.5
1.增加了TimerCallBack，通过CatCache和EventDispatch实现了按时间触发的消息队列，重启服务器可恢复，使用简单。

需要开启CatCache,延迟调用Model方法。
```php
 $token = yield TimerCallBack::addTimer(2,TestModel::class,'testTimerCall',[123]);
 $this->http_output->end($token);
 
 public function testTimerCall($value,$token)
 {
     var_dump($token);
     TimerCallBack::ack($token);
 }
```

2.修复了集群下的一些错误。

# 2.7.5.1
1.新增Actor模型，可创建Actor，加速游戏开发。
```
 Actor::create(TestActor::class, "actor");
 Actor::call("actor", "test");
 Actor::call("actor", "destroy");
```
2.修复404页面http头不对的问题

# 2.7.5.2
1.完善Actor，支持自动恢复状态

2.修复进程间RPC的一个bug

3.修复EventDispatch的一个bug

# 2.7.5.3
1.优化定时器

2.优化协程

3.redis的set支持标签
```php
$result = yield $this->redis_pool->getCoroutine()->set('testroute', 21,["XX","EX"=>10]);
```
# 2.7.6
Actor专版

1.Actor名称重复性检测，集群中不允许出现重名的Actor

2.Actor间可以自由RPC，支持集群

3.Actor中可以使用一切异步客户端，支持协程,可以调用Model，Task

4.Actor支持事务，保证事务执行的顺序
```php
$rpc = Actor::getRpc("Test2");
try {
    $beginid = yield $rpc->beginCo();
    $result = yield $rpc->test1();
    $result = yield $rpc->test2();
    //var_dump($result);
    $result = yield $rpc->test3();
    //var_dump($result);
} finally {
    //var_dump("finally end");
    $rpc->end();
}
```
5.Actor现在默认会落地存盘，如不手动调用Destroy，重启服务器会自动恢复Actor，可以删除cache文件夹清理。

# 2.7.7-beta
1.需要PHP7.1.6以上版本

2.依赖项全部升级到最新

3.全新控制台，用户可以自定义控制台命令，app/Console下命令将会自动加载

# 2.7.7
正式版，已经历线上产品验证，稳定

# 2.7.8
1.修复bug

2.business配置支持路由到控制器
```php
'default' =>
        [
            'index' => ['TestController', 'test'] //转到控制器
        ]
    ,
```
通过传递给index一个数组即可路由到TestController的test方法，记住这里方法不需要加前缀

# 3.0-beta

SD3.0版本beta版本，支持swoole2.0协程，需要安装swoole2.0扩展，编译命令如下：
```
./configure --enable-async-redis  --enable-openssl --enable-coroutine
```
2.0版本迁移3.0需要做的修改，非常简单

1.去除业务代码中所有的yield字样

2.如果使用了协程超时，需要修改为这样，通过set回调函数设置协程的参数
```php
$data = EventDispatcher::getInstance()->addOnceCoroutine('unlock', function (EventCoroutine $e) {
            $e->setTimeout(10000);
        });
```

请注意这是一个测试版本，并没达到线上运行水平，已知框架问题和swoole2.0扩展问题还在积极修复。

# 3.0.2
3.0正式版，需要swoole扩展版本为2.1.1

# 3.1.0
1.mysql改版

2.增加Whoops

3.模板引擎更换为Laravel-blade

# 3.1.1
1.Task调用直接返回结果不需要使用coroutineSend

2.Controller自动destory，不再需要手动执行了，send命令也没有autoDestory参数了

3.Controller增加interrupt指令用于中断后面执行的代码

4.增加了一个错误搜集的模块error.php需要结合钉钉机器人实现错误推送