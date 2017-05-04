<?php
namespace Server;

use Server\Asyn\AsynPool;
use Server\Asyn\HttpClient\HttpClientPool;
use Server\Asyn\Mysql\Miner;
use Server\Asyn\Mysql\MysqlAsynPool;
use Server\Asyn\Redis\RedisAsynPool;
use Server\Asyn\Redis\RedisLuaManager;
use Server\Components\Consul\ConsulHelp;
use Server\Components\Consul\ConsulServices;
use Server\Components\Dispatch\DispatchHelp;
use Server\Components\Reload\ReloadHelp;
use Server\CoreBase\SwooleException;
use Server\CoreBase\TimerTask;
use Server\Coroutine\Coroutine;
use Server\Test\TestModule;

define("SERVER_DIR", __DIR__);
define("APP_DIR", __DIR__ . "/../app");
define("WWW_DIR", __DIR__ . "/../www");

/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-14
 * Time: 上午9:18
 */
abstract class SwooleDistributedServer extends SwooleWebSocketServer
{
    const SERVER_NAME = "SERVER";
    /**
     * 实例
     * @var SwooleServer
     */
    private static $instance;
    /**
     * @var RedisAsynPool
     */
    public $redis_pool;
    /**
     * @var MysqlAsynPool
     */
    public $mysql_pool;
    /**
     * 覆盖set配置
     * @var array
     */
    public $overrideSetConfig = [];
    /**
     * 404缓存
     * @var string
     */
    public $cache404;
    /**
     * 生成task_id的原子
     */
    public $task_atomic;
    /**
     * task_id和pid的映射
     */
    public $tid_pid_table;
    /**
     * 中断task的id内存锁
     */
    public $task_lock;
    /**
     * @var \Redis
     */
    protected $redis_client;
    /**
     * @var Miner
     */
    protected $mysql_client;
    /**
     * dispatch fd
     * @var array
     */
    protected $dispatchClientFds = [];
    /**
     * dispatch 端口
     * @var int
     */
    protected $dispatch_port;
    /**
     * 共享内存表
     * @var \swoole_table
     */
    protected $uid_fd_table;
    /**
     * 连接池进程
     * @var
     */
    protected $pool_process;
    /**
     * 分布式系统服务器唯一标识符
     * @var int
     */
    private $USID;
    /**
     * 多少人启用task进行发送
     * @var
     */
    private $send_use_task_num;
    /**
     * 初始化的锁
     * @var \swoole_lock
     */
    private $initLock;
    /**
     * 连接池
     * @var
     */
    private $asynPools;

    /**
     * SwooleDistributedServer constructor.
     */
    public function __construct()
    {
        self::$instance =& $this;
        $this->name = self::SERVER_NAME;
        parent::__construct();
    }

    /**
     * 获取实例
     * @return SwooleDistributedServer
     */
    public static function &get_instance()
    {
        return self::$instance;
    }

    public function start()
    {
        //加载redis的lua脚本
        $redis_pool = new RedisAsynPool($this->config, $this->config->get('redis.active'));
        $redisLuaManager = new RedisLuaManager($redis_pool->getSync());
        $redisLuaManager->registerFile(__DIR__ . "/../lua");
        $redis_pool->getSync()->close();
        $redis_pool = null;
        return parent::start();
    }

    /**
     * 清除状态
     * @throws SwooleException
     */
    public function clearState()
    {
        print("是否清除Redis上的用户状态信息(y/n)？");
        $clear_redis = shell_read();
        if (strtolower($clear_redis) == 'y') {
            echo "[初始化] 清除Redis上用户状态。\n";
            $redis_pool = new RedisAsynPool($this->config, $this->config->get('redis.active'));
            $redis_pool->getSync()->del(SwooleMarco::redis_uid_usid_hash_name);
            $redis_pool->getSync()->close();
            unset($redis_pool);
        }
    }

    /**
     * 获取同步mysql
     * @return Miner
     * @throws SwooleException
     */
    public function getMysql()
    {
        return $this->mysql_pool->getSync();
    }

    /**
     * 设置配置
     */
    public function setConfig()
    {
        parent::setConfig();
        $this->send_use_task_num = $this->config['server']['send_use_task_num'];
    }

    /**
     * 开始前创建共享内存保存USID值
     */
    public function beforeSwooleStart()
    {
        parent::beforeSwooleStart();
        //创建uid->fd共享内存表
        $this->uid_fd_table = new \swoole_table(65536);
        $this->uid_fd_table->column('fd', \swoole_table::TYPE_INT, 8);
        $this->uid_fd_table->create();
        //创建task用的taskid
        $this->task_atomic = new \swoole_atomic(0);
        //创建task用的id->pid共享内存表不至于同时超过1024个任务吧
        $this->tid_pid_table = new \swoole_table(1024);
        $this->tid_pid_table->column('pid', \swoole_table::TYPE_INT, 8);
        $this->tid_pid_table->column('des', \swoole_table::TYPE_STRING, 50);
        $this->tid_pid_table->column('start_time', \swoole_table::TYPE_INT, 8);
        $this->tid_pid_table->create();
        //创建task用的锁
        $this->task_lock = new \swoole_lock(SWOOLE_MUTEX);
        //reload监控进程
        ReloadHelp::startProcess();
        //consul进程
        ConsulHelp::startProcess();
        //开启一个UDP用于发graylog
        if($this->config->get('log.active')=='graylog'){
            $udp_port =  $this->server->listen($this->config['tcp']['socket'], $this->config['log']['graylog']['udp_send_port'], SWOOLE_SOCK_UDP);
            $udp_port->on('packet',function (){
            });
        }
        if ($this->config->get('use_dispatch')) {
            //创建dispatch端口用于连接dispatch
            $this->dispatch_port = $this->server->listen($this->config['tcp']['socket'], $this->config['server']['dispatch_port'], SWOOLE_SOCK_TCP);
            $this->dispatch_port->set($this->probuf_set);
            $this->dispatch_port->on('close', function ($serv, $fd) {
                print_r("Remove a dispatcher.\n");
                for ($i = 0; $i < $this->worker_num + $this->task_num; $i++) {
                    if ($i == $serv->worker_id) continue;
                    $data = $this->packSerevrMessageBody(SwooleMarco::REMOVE_DISPATCH_CLIENT, $fd);
                    $serv->sendMessage($data, $i);
                }
                $this->sendToAllWorks(SwooleMarco::REMOVE_DISPATCH_CLIENT, $fd, DispatchHelp::class . "::removeDispatch");
            });

            $this->dispatch_port->on('receive', function ($serv, $fd, $from_id, $data) {
                $data = unpack($this->package_length_type . "1length/a*data", $data)['data'];
                $unserialize_data = unserialize($data);
                $type = $unserialize_data['type'];
                $message = $unserialize_data['message'];
                switch ($type) {
                    case SwooleMarco::MSG_TYPE_USID://获取服务器唯一id
                        print_r("Find a new dispatcher.\n");
                        $uns_data = unserialize($message);
                        $uns_data['fd'] = $fd;
                        $fdinfo = $this->server->connection_info($fd);
                        $uns_data['remote_ip'] = $fdinfo['remote_ip'];
                        $this->sendToAllWorks($type, $uns_data, DispatchHelp::class . "::addDispatch");
                        break;
                    case SwooleMarco::MSG_TYPE_SEND://发送消息
                        $this->sendToUid($message['uid'], $message['data'], true);
                        break;
                    case SwooleMarco::MSG_TYPE_SEND_BATCH://批量消息
                        $this->sendToUids($message['uids'], $message['data'], true);
                        break;
                    case SwooleMarco::MSG_TYPE_SEND_ALL://广播消息
                        $serv->task($data);
                        break;
                    case SwooleMarco::MSG_TYPE_KICK_UID://踢人
                        $this->kickUid($message['uid'], true);
                        break;
                }
            });
        }
        $this->initLock = new \swoole_lock(SWOOLE_RWLOCK);
    }

    /**
     * 发送给所有的进程，$callStaticFuc为静态方法,会在每个进程都执行
     * @param $type
     * @param $uns_data
     * @param string $callStaticFuc
     */
    public function sendToAllWorks($type, $uns_data, string $callStaticFuc)
    {
        $send_data = $this->packSerevrMessageBody($type, $uns_data, $callStaticFuc);
        for ($i = 0; $i < $this->worker_num + $this->task_num; $i++) {
            if ($this->server->worker_id == $i) continue;
            $this->server->sendMessage($send_data, $i);
        }
        //自己的进程是收不到消息的所以这里执行下
        call_user_func($callStaticFuc, $uns_data);
    }

    /**
     * 设置服务器配置参数
     * @return array
     */
    public function setServerSet()
    {
        $set = $this->config->get('server.set', []);
        $set = array_merge($set, $this->probuf_set);
        $set = array_merge($set, $this->overrideSetConfig);
        $this->worker_num = $set['worker_num'];
        $this->task_num = $set['task_worker_num'];
        return $set;
    }

    /**
     * 被DispatchHelp调用
     * 移除dispatch
     * @param $fd
     */
    public function removeDispatch($fd)
    {
        unset($this->dispatchClientFds[$fd]);
    }

    /**
     * 被DispatchHelp调用
     * 添加一个dispatch
     * @param $data
     */
    public function addDispatch($data)
    {
        $this->USID = $data['usid'];
        $this->dispatchClientFds[$data['fd']] = $data['fd'];
    }

    /**
     * 向uid发送消息
     * @param $uid
     * @param $data
     * @param $fromDispatch
     */
    public function sendToUid($uid, $data, $fromDispatch = false)
    {
        if (!$fromDispatch) {
            $data = $this->encode($this->pack->pack($data));
        }
        if ($this->uid_fd_table->exist($uid)) {//本机处理
            $fd = $this->uid_fd_table->get($uid)['fd'];
            $this->send($fd, $data);
        } else {
            if ($fromDispatch) return;
            $this->sendToDispatchMessage(SwooleMarco::MSG_TYPE_SEND, ['data' => $data, 'uid' => $uid]);
        }
    }

    /**
     * 随机选择一个dispatch发送消息
     * @param $data
     */
    private function sendToDispatchMessage($type, $data)
    {
        $send_data = $this->packSerevrMessageBody($type, $data);
        $fd = null;
        if (count($this->dispatchClientFds) > 0) {
            $fd = $this->dispatchClientFds[array_rand($this->dispatchClientFds)];
        }
        if ($fd != null) {
            $this->server->send($fd, $this->encode($send_data));
        } else {
            //如果没有dispatch那么MSG_TYPE_SEND_BATCH这个消息不需要发出，因为本机已经处理过可以发送的uid了
            if ($type == SwooleMarco::MSG_TYPE_SEND_BATCH) return;
            if ($this->isTaskWorker()) {
                $this->onSwooleTask($this->server, 0, 0, $send_data);
            } else {
                $this->server->task($send_data);
            }
        }
    }

    /**
     * task异步任务
     * @param $serv
     * @param $task_id
     * @param $from_id
     * @param $data
     * @return mixed|null
     * @throws SwooleException
     */
    public function onSwooleTask($serv, $task_id, $from_id, $data)
    {
        if (is_string($data)) {
            $unserialize_data = unserialize($data);
        } else {
            $unserialize_data = $data;
        }
        $type = $unserialize_data['type']??'';
        $message = $unserialize_data['message']??'';
        switch ($type) {
            case SwooleMarco::MSG_TYPE_SEND_BATCH://发送消息
                foreach ($message['fd'] as $fd) {
                    $this->send($fd, $message['data']);
                }
                return null;
            case SwooleMarco::MSG_TYPE_SEND_ALL://发送广播
                foreach ($serv->connections as $fd) {
                    if (in_array($fd, $this->dispatchClientFds)) {
                        continue;
                    }
                    $this->send($fd, $message['data']);
                }
                return null;
            case SwooleMarco::MSG_TYPE_SEND_GROUP://群组
                $uids = $this->getRedis()->sMembers(SwooleMarco::redis_group_hash_name_prefix . $message['groupId']);
                foreach ($uids as $uid) {
                    if ($this->uid_fd_table->exist($uid)) {
                        $fd = $this->uid_fd_table->get($uid)['fd'];
                        $this->send($fd, $message['data']);
                    }
                }
                return null;
            case SwooleMarco::SERVER_TYPE_TASK://task任务
                $task_name = $message['task_name'];
                $task = $this->loader->task($task_name, $this);
                $task_fuc_name = $message['task_fuc_name'];
                $task_data = $message['task_fuc_data'];
                $task_context = $message['task_context'];
                if (method_exists($task, $task_fuc_name)) {
                    //给task做初始化操作
                    $task->initialization($task_id, $from_id,$this->server->worker_pid, $task_name, $task_fuc_name, $task_context);
                    $result = Coroutine::startCoroutine([$task, $task_fuc_name], $task_data);
                } else {
                    throw new SwooleException("method $task_fuc_name not exist in $task_name");
                }
                $task->destroy();
                return $result;
            default:
                return parent::onSwooleTask($serv, $task_id, $from_id, $data);
        }
    }

    /**
     * 获取同步redis
     * @return \Redis
     * @throws SwooleException
     */
    public function getRedis()
    {
        return $this->redis_pool->getSync();
    }

    /**
     * 批量发送消息
     * @param $uids
     * @param $data
     * @param $fromDispatch
     */
    public function sendToUids($uids, $data, $fromDispatch = false)
    {
        if (!$fromDispatch) {
            $data = $this->encode($this->pack->pack($data));
        }
        $current_fds = [];
        foreach ($uids as $key => $uid) {
            if ($this->uid_fd_table->exist($uid)) {
                $current_fds[] = $this->uid_fd_table->get($uid)['fd'];
                unset($uids[$key]);
            }
        }
        if (count($current_fds) > $this->send_use_task_num) {//过多人就通过task
            $task_data = $this->packSerevrMessageBody(SwooleMarco::MSG_TYPE_SEND_BATCH, ['data' => $data, 'fd' => $current_fds]);
            if ($this->isTaskWorker()) {
                $this->onSwooleTask($this->server, 0, 0, $task_data);
            } else {
                $this->server->task($task_data);
            }
        } else {
            foreach ($current_fds as $fd) {
                $this->send($fd, $data);
            }
        }
        if ($fromDispatch) return;
        //本机处理不了的发给dispatch
        if (count($uids) > 0) {
            $this->sendToDispatchMessage(SwooleMarco::MSG_TYPE_SEND_BATCH, ['data' => $data, 'uids' => array_values($uids)]);
        }
    }

    /**
     * 踢用户下线
     * @param $uid
     * @param bool $fromDispatch
     */
    public function kickUid($uid, $fromDispatch = false)
    {
        if ($this->uid_fd_table->exist($uid)) {//本机处理
            $fd = $this->uid_fd_table->get($uid)['fd'];
            $this->close($fd);
        } else {
            if ($fromDispatch) return;
            $usid = $this->getRedis()->hGet(SwooleMarco::redis_uid_usid_hash_name, $uid);
            $this->sendToDispatchMessage(SwooleMarco::MSG_TYPE_KICK_UID, ['usid' => $usid, 'uid' => $uid]);
        }
    }

    /**
     * PipeMessage
     * @param $serv
     * @param $from_worker_id
     * @param $message
     */
    public function onSwoolePipeMessage($serv, $from_worker_id, $message)
    {
        parent::onSwoolePipeMessage($serv, $from_worker_id, $message);
        $data = unserialize($message);
        if (!empty($data['func'])) {
            call_user_func($data['func'], $data['message']);
        }
    }

    /**
     * 添加AsynPool
     * @param $name
     * @param AsynPool $pool
     * @throws SwooleException
     */
    public function addAsynPool($name, AsynPool $pool)
    {
        if (array_key_exists($name, $this->asynPools)) {
            throw  new SwooleException('pool key is exists!');
        }
        $this->asynPools[$name] = $pool;
    }

    /**
     * 获取连接池
     * @param $name
     * @return AsynPool
     */
    public function getAsynPool($name)
    {
        return $this->asynPools[$name];
    }

    /**
     * 重写onSwooleWorkerStart方法，添加异步redis
     * @param $serv
     * @param $workerId
     * @throws SwooleException
     */
    public function onSwooleWorkerStart($serv, $workerId)
    {
        parent::onSwooleWorkerStart($serv, $workerId);
        $this->initAsynPools();
        $this->redis_pool = $this->asynPools['redisPool'];
        $this->mysql_pool = $this->asynPools['mysqlPool'];
        //进程锁保证只有一个进程会执行以下的代码,reload也不会执行
        if (!$this->isTaskWorker() && $this->initLock->trylock()) {
            //进程启动后进行开服的初始化
            Coroutine::startCoroutine([$this,'onOpenServiceInitialization']);
            if (SwooleServer::$testUnity) {
                new TestModule(SwooleServer::$testUnityDir);
            }
            $this->initLock->lock_read();
        }
        //最后一个worker处理启动定时器
        if ($workerId == $this->worker_num - 1) {
            //启动定时器任务
            new TimerTask($this->config);
        }
        //第一个worker启动Consul健康检查
        if ($workerId == 0) {
            if (get_instance()->config->get('consul_enable', false)) {
                Coroutine::startCoroutine(function () {
                    yield ConsulServices::getInstance()->serviceHealthCheck();
                });
            }
        }
    }

    /**
     * 初始化各种连接池
     */
    public function initAsynPools()
    {
        $this->asynPools = [
            'redisPool' => new RedisAsynPool($this->config, $this->config->get('redis.active')),
            'mysqlPool' => new MysqlAsynPool($this->config, $this->config->get('mysql.active')),
            'consul' => new HttpClientPool($this->config,'http://127.0.0.1:8500')
        ];
    }

    /**
     * 开服初始化(支持协程)
     * @return mixed
     */
    public function onOpenServiceInitialization()
    {
        if ($this->config->get('autoClearGroup', false)) {
            $this->delAllGroups();
            print_r("[初始化] 清除redis上所有群信息。\n");
        }
        if($this->config['consul_enable']){
            //选举leader
            $ConsulModel = $this->loader->model('ConsulModel',$this);
            yield $ConsulModel->leader();
        }
    }

    /**
     * 删除所有的群
     */
    public function delAllGroups()
    {
        if ($this->isTaskWorker()) {
            $groups = $this->getAllGroups(null);
            foreach ($groups as $key => $group_id) {
                $groups[$key] = SwooleMarco::redis_group_hash_name_prefix . $group_id;
            }
            $groups[] = SwooleMarco::redis_groups_hash_name;
            //删除所有的群和群管理
            $this->getRedis()->del($groups);
        } else {
            $this->getAllGroups(function ($groups) {
                foreach ($groups as $key => $group_id) {
                    $groups[$key] = SwooleMarco::redis_group_hash_name_prefix . $group_id;
                }
                $groups[] = SwooleMarco::redis_groups_hash_name;
                //删除所有的群和群管理
                $this->redis_pool->del($groups, null);
            });
        }
    }

    /**
     * 获取所有的群id(异步时候需要提供callback,task可以直接返回结果)
     * @param $callback
     * @return array
     */
    public function getAllGroups($callback)
    {
        if ($this->isTaskWorker()) {
            return $this->getRedis()->sMembers(SwooleMarco::redis_groups_hash_name);
        } else {
            $this->redis_pool->sMembers(SwooleMarco::redis_groups_hash_name, $callback);
        }
    }

    /**
     * 连接断开
     * @param $serv
     * @param $fd
     */
    public function onSwooleClose($serv, $fd)
    {
        $info = $serv->connection_info($fd, 0, true);
        $uid = $info['uid']??0;
        if (!empty($uid)) {
            Coroutine::startCoroutine([$this, 'onUidCloseClear'], [$uid]);
            $this->unBindUid($uid);
        }
        parent::onSwooleClose($serv, $fd);
    }

    /**
     * ｗｅｂｓｏｃｋｅｔ的连接断开
     * @param $serv
     * @param $fd
     */
    public function onSwooleWSClose($serv, $fd)
    {
        $this->onSwooleClose($serv,$fd);
    }

    /**
     * 解绑uid，链接断开自动解绑
     * @param $uid
     */
    public function unBindUid($uid)
    {
        //更新共享内存
        $ok = $this->uid_fd_table->del($uid);
        //更新映射表
        if ($ok) {//说明是本机绑定的uid
            $this->getRedis()->hDel(SwooleMarco::redis_uid_usid_hash_name, $uid);
        }
    }

    /**
     * 当一个绑定uid的连接close后的清理
     * 支持协程
     * @param $uid
     */
    abstract public function onUidCloseClear($uid);

    /**
     * 将fd绑定到uid,uid不能为0
     * @param $fd
     * @param $uid
     * @param bool $isKick 是否踢掉uid上一个的链接
     */
    public function bindUid($fd, $uid, $isKick = true)
    {
        if ($isKick) {
            $this->kickUid($uid, false);
        }
        $this->getRedis()->hSet(SwooleMarco::redis_uid_usid_hash_name, $uid, $this->USID);
        //将这个fd与当前worker进行绑定
        $this->server->bind($fd, $uid);
        //加入共享内存
        $this->uid_fd_table->set($uid, ['fd' => $fd]);
    }

    /**
     * uid是否在线(协程)
     * @param $uid
     * @return int
     * @throws SwooleException
     */
    public function coroutineUidIsOnline($uid)
    {
        return yield $this->redis_pool->getCoroutine()->hExists(SwooleMarco::redis_uid_usid_hash_name, $uid);
    }


    /**
     * 获取在线人数(协程)
     * @return int
     * @throws SwooleException
     */
    public function coroutineCountOnline()
    {
        return yield $this->redis_pool->getCoroutine()->hLen(SwooleMarco::redis_uid_usid_hash_name);
    }

    /**
     * 获取所有的群id（协程）
     * @return array
     * @throws SwooleException
     */
    public function coroutineGetAllGroups()
    {
        return yield $this->redis_pool->getCoroutine()->sMembers(SwooleMarco::redis_groups_hash_name);
    }

    /**
     * 添加到群(可以支持批量,实际是否支持根据sdk版本测试)
     * @param $uid int | array
     * @param $group_id int
     */
    public function addToGroup($uid, $group_id)
    {
        if ($this->isTaskWorker()) {
            //放入群管理中
            $this->getRedis()->sAdd(SwooleMarco::redis_groups_hash_name, $group_id);
            //放入对应的群中
            $this->getRedis()->sAdd(SwooleMarco::redis_group_hash_name_prefix . $group_id, $uid);
        } else {
            //放入群管理中
            $this->redis_pool->sAdd(SwooleMarco::redis_groups_hash_name, $group_id, null);
            //放入对应的群中
            $this->redis_pool->sAdd(SwooleMarco::redis_group_hash_name_prefix . $group_id, $uid, null);
        }
    }


    /**
     * 从群里移除(可以支持批量,实际是否支持根据sdk版本测试)
     * @param $uid int | array
     * @param $group_id
     */
    public function removeFromGroup($uid, $group_id)
    {
        if ($this->isTaskWorker()) {
            $this->getRedis()->sRem(SwooleMarco::redis_group_hash_name_prefix . $group_id, $uid);
        } else {
            $this->redis_pool->sRem(SwooleMarco::redis_group_hash_name_prefix . $group_id, $uid, null);
        }
    }

    /**
     * 删除群
     * @param $group_id
     */
    public function delGroup($group_id)
    {
        if ($this->isTaskWorker()) {
            //从群管理中删除
            $this->getRedis()->sRem(SwooleMarco::redis_groups_hash_name, $group_id);
            //删除这个群
            $this->getRedis()->del(SwooleMarco::redis_group_hash_name_prefix . $group_id);
        } else {
            //从群管理中删除
            $this->redis_pool->sRem(SwooleMarco::redis_groups_hash_name, $group_id, null);
            //删除这个群
            $this->redis_pool->del(SwooleMarco::redis_group_hash_name_prefix . $group_id, null);
        }
    }

    /**
     * 获取群的人数（协程）
     * @param $group_id
     * @return int
     * @throws SwooleException
     */
    public function coroutineGetGroupCount($group_id)
    {
        return yield $this->redis_pool->getCoroutine()->sCard(SwooleMarco::redis_group_hash_name_prefix . $group_id);
    }

    /**
     * 获取群成员uids (协程)
     * @param $group_id
     * @return array
     * @throws SwooleException
     */
    public function coroutineGetGroupUids($group_id)
    {
        return yield $this->redis_pool->getCoroutine()->sMembers(SwooleMarco::redis_group_hash_name_prefix . $group_id);
    }

    /**
     * 广播
     * @param $data
     */
    public function sendToAll($data)
    {
        $data = $this->encode($this->pack->pack($data));
        $this->sendToDispatchMessage(SwooleMarco::MSG_TYPE_SEND_ALL, ['data' => $data]);
    }

    /**
     * 发送给群
     * @param $groupId
     * @param $data
     */
    public function sendToGroup($groupId, $data)
    {
        $data = $this->encode($this->pack->pack($data));
        $this->sendToDispatchMessage(SwooleMarco::MSG_TYPE_SEND_GROUP, ['data' => $data, 'groupId' => $groupId]);
    }

    /**
     * 向task发送强制停止命令
     * @param $task_id
     */
    public function stopTask($task_id)
    {
        $task_pid = get_instance()->tid_pid_table->get($task_id)['pid'];
        if($task_pid!=null) {
            posix_kill($task_pid, SIGKILL);
            get_instance()->tid_pid_table->del($task_id);
        }
    }

    /**
     * 获取服务器上正在运行的Task
     * @return array
     */
    public function getServerAllTaskMessage()
    {
        $tasks = [];
        foreach ($this->tid_pid_table as $id => $row) {
            if ($id != 0) {
                $row['task_id'] = $id;
                $row['run_time'] = time() - $row['start_time'];
                $tasks[] = $row;
            }
        }
        return $tasks;
    }
}