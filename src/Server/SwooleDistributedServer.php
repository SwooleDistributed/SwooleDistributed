<?php

namespace Server;

use Server\Asyn\HttpClient\HttpClientPool;
use Server\Asyn\MQTT\Utility;
use Server\Asyn\Mysql\Miner;
use Server\Asyn\Mysql\MysqlAsynPool;
use Server\Asyn\Redis\RedisAsynPool;
use Server\Asyn\Redis\RedisLuaManager;
use Server\Components\Backstage\BackstageProcess;
use Server\Components\CatCache\CatCacheProcess;
use Server\Components\CatCache\TimerCallBack;
use Server\Components\Cluster\ClusterHelp;
use Server\Components\Cluster\ClusterProcess;
use Server\Components\Consul\ConsulHelp;
use Server\Components\Consul\ConsulProcess;
use Server\Components\Event\EventDispatcher;
use Server\Components\GrayLog\GrayLogHelp;
use Server\Components\Process\ProcessManager;
use Server\Components\SDHelp\SDHelpProcess;
use Server\Components\TimerTask\Timer;
use Server\Components\TimerTask\TimerTask;
use Server\CoreBase\Actor;
use Server\CoreBase\ControllerFactory;
use Server\CoreBase\ModelFactory;
use Server\CoreBase\SwooleException;
use Server\Coroutine\Coroutine;
use Server\Memory\Pool;
use Server\Test\TestModule;

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
     * 连接池进程
     * @var
     */
    protected $pool_process;
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
    private $asynPools = [];

    /**
     * @var
     */
    private $bind_ip;

    /**
     * 重载锁
     * @var array
     */
    private $reloadLockMap = [];

    /**
     * SwooleDistributedServer constructor.
     */
    public function __construct()
    {
        self::$instance =& $this;
        $this->name = self::SERVER_NAME;
        parent::__construct();
        $this->send_use_task_num = $this->config['server']['send_use_task_num'];
        if (!checkExtension()) {
            exit(-1);
        }
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
        if ($this->config->get('redis.enable', true)) {
            //加载redis的lua脚本
            $redis_pool = new RedisAsynPool($this->config, $this->config->get('redis.active'));
            $redisLuaManager = new RedisLuaManager($redis_pool->getSync());
            $redisLuaManager->registerFile(LUA_DIR);
            $redis_pool->getSync()->close();
            $redis_pool = null;
        }
        //非集群默认是leader
        if (!$this->isCluster()) {
            Start::setLeader(true);
        }
        parent::start();
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
     * 开始前创建共享内存保存USID值
     */
    public function beforeSwooleStart()
    {
        parent::beforeSwooleStart();
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
        //开启用户进程
        $this->startProcess();
        //开启一个UDP用于发graylog
        GrayLogHelp::init();
        //开启Cluster端口
        ClusterHelp::getInstance()->buildPort();
        //Timer
        Timer::init();
        //init锁
        $this->initLock = new \swoole_lock(SWOOLE_RWLOCK);
        //reload锁
        for ($i = 0; $i < $this->worker_num; $i++) {
            $lock = new \swoole_lock(SWOOLE_MUTEX);
            $this->reloadLockMap[$i] = $lock;
        }
    }

    /**
     * 创建用户进程
     */
    public function startProcess()
    {
        //timerTask,reload进程
        ProcessManager::getInstance()->addProcess(SDHelpProcess::class);
        //consul进程
        if ($this->config->get('consul.enable', false)) {
            ProcessManager::getInstance()->addProcess(ConsulProcess::class);
        }
        if ($this->config->get('backstage.enable', false)) {
            ProcessManager::getInstance()->addProcess(BackstageProcess::class);
        }
        //Cluster进程
        ProcessManager::getInstance()->addProcess(ClusterProcess::class);
        //CatCache进程
        if ($this->config->get('catCache.enable', false)) {
            ProcessManager::getInstance()->addProcess(CatCacheProcess::class);
        }
    }

    /**
     * 发送给所有的进程，$callStaticFuc为静态方法,会在每个进程都执行
     * @param $type
     * @param $uns_data
     * @param string $callStaticFuc
     */
    public function sendToAllWorks($type, $uns_data, string $callStaticFuc)
    {
        $send_data = $this->packServerMessageBody($type, $uns_data, $callStaticFuc);
        for ($i = 0; $i < $this->worker_num + $this->task_num; $i++) {
            if ($this->server->worker_id == $i) continue;
            $this->server->sendMessage($send_data, $i);
        }
        //自己的进程是收不到消息的所以这里执行下
        \co::call_user_func($callStaticFuc, $uns_data);
    }

    /**
     * 发送给所有的异步进程，$callStaticFuc为静态方法,会在每个进程都执行
     * @param $type
     * @param $uns_data
     * @param string $callStaticFuc
     */
    public function sendToAllAsynWorks($type, $uns_data, string $callStaticFuc)
    {
        $send_data = $this->packServerMessageBody($type, $uns_data, $callStaticFuc);
        for ($i = 0; $i < $this->worker_num; $i++) {
            if ($this->server->worker_id == $i) continue;
            $this->server->sendMessage($send_data, $i);
        }
        //自己的进程是收不到消息的所以这里执行下
        \co::call_user_func($callStaticFuc, $uns_data);
    }

    /**
     * 发送给随机进程
     * @param $type
     * @param $uns_data
     * @param string $callStaticFuc
     */
    public function sendToRandomWorker($type, $uns_data, string $callStaticFuc)
    {
        $send_data = get_instance()->packServerMessageBody($type, $uns_data, $callStaticFuc);
        $id = rand(0, get_instance()->worker_num - 1);
        if ($this->server->worker_id == $id) {
            //自己的进程是收不到消息的所以这里执行下
            \co::call_user_func($callStaticFuc, $uns_data);
        } else {
            get_instance()->server->sendMessage($send_data, $id);
        }
    }

    /**
     * 发送给指定进程
     * @param $workerId
     * @param $type
     * @param $uns_data
     * @param string $callStaticFuc
     */
    public function sendToOneWorker($workerId, $type, $uns_data, string $callStaticFuc)
    {
        $send_data = get_instance()->packServerMessageBody($type, $uns_data, $callStaticFuc);
        if ($this->server->worker_id == $workerId) {
            //自己的进程是收不到消息的所以这里执行下
            \co::call_user_func($callStaticFuc, $uns_data);
        } else {
            get_instance()->server->sendMessage($send_data, $workerId);
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
        $type = $data['type'] ?? '';
        $message = $data['message'] ?? '';
        switch ($type) {
            case SwooleMarco::MSG_TYPE_SEND_BATCH://发送消息
                foreach ($message['fd'] as $fd) {
                    $this->send($fd, $message['data'], true);
                }
                return null;
            case SwooleMarco::MSG_TYPE_SEND_ALL://发送广播
                foreach ($this->uid_fd_table as $row) {
                    $this->send($row['fd'], $message['data'], true);
                }
                return null;
            case SwooleMarco::MSG_TYPE_SEND_ALL_FD;//发送广播
                foreach ($serv->connections as $fd) {
                    $serv->send($fd, $message['data'], true);
                }
                return null;
            case SwooleMarco::SERVER_TYPE_TASK://task任务
                $task_name = $message['task_name'];
                $task = $this->loader->task($task_name, $this);
                $task_fuc_name = $message['task_fuc_name'];
                $task_data = $message['task_fuc_data'];
                $task_context = $message['task_context'];
                $call = [$task->getProxy(), $task_fuc_name];
                if (is_callable($call)) {
                    //给task做初始化操作
                    $task->initialization($task_id, $from_id, $this->server->worker_pid, $task_name, $task_fuc_name, $task_context);
                    $result = call_user_func_array($call, $task_data);
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
     * 是否是重载
     */
    protected function isReload()
    {
        $lock = $this->reloadLockMap[$this->workerId];
        $result = $lock->trylock();
        return !$result;
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
     * 广播(全部FD)
     * @param $data
     * @param bool $fromDispatch
     */
    public function sendToAllFd($data, $fromDispatch = false)
    {
        $send_data = $this->packServerMessageBody(SwooleMarco::MSG_TYPE_SEND_ALL_FD, ['data' => $data]);
        if ($this->isTaskWorker()) {
            $this->onSwooleTask($this->server, 0, 0, $send_data);
        } else {
            if ($this->task_num > 0) {
                $this->server->task($send_data);
            } else {
                foreach ($this->server->connections as $fd) {
                    $this->server->send($fd, $data, true);
                }
            }
        }
        if ($fromDispatch) return;
        if ($this->isCluster()) {
            ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_sendToAllFd($data);
        }
    }

    /**
     * 广播
     * @param $data
     * @param bool $fromDispatch
     */
    public function sendToAll($data, $fromDispatch = false)
    {
        $send_data = $this->packServerMessageBody(SwooleMarco::MSG_TYPE_SEND_ALL, ['data' => $data]);
        if ($this->isTaskWorker()) {
            $this->onSwooleTask($this->server, 0, 0, $send_data);
        } else {
            if ($this->task_num > 0) {
                $this->server->task($send_data);
            } else {
                foreach ($this->uid_fd_table as $row) {
                    $this->send($row['fd'], $data, true);
                }
            }
        }
        if ($fromDispatch) return;
        if ($this->isCluster()) {
            ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_sendToAll($data);
        }
    }

    /**
     * 向uid发送消息
     * @param $uid
     * @param $data
     * @param $fromDispatch
     */
    public function sendToUid($uid, $data, $fromDispatch = false)
    {
        if ($this->uid_fd_table->exist($uid)) {//本机处理
            $fd = $this->uid_fd_table->get($uid)['fd'];
            $this->send($fd, $data, true);
        } else {
            if ($fromDispatch) return;
            if ($this->isCluster()) {
                ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_sendToUid($uid, $data);
            }
        }
    }

    /**
     * @param $uid
     * @param $data
     * @param $topic
     */
    public function pubToUid($uid, $data, $topic)
    {
        if ($this->uid_fd_table->exist($uid)) {
            $fd = $this->uid_fd_table->get($uid)['fd'];
            $this->send($fd, $data, true, $topic);
        }
    }

    /**
     * 批量发送消息
     * @param $uids
     * @param $data
     * @param $fromDispatch
     */
    public function sendToUids($uids, $data, $fromDispatch = false)
    {
        $current_fds = [];
        foreach ($uids as $key => $uid) {
            if ($this->uid_fd_table->exist($uid)) {
                $current_fds[] = $this->uid_fd_table->get($uid)['fd'];
                unset($uids[$key]);
            }
        }
        if (count($current_fds) > $this->send_use_task_num && $this->task_num > 0) {//过多人就通过task
            $task_data = $this->packServerMessageBody(SwooleMarco::MSG_TYPE_SEND_BATCH, ['data' => $data, 'fd' => $current_fds]);
            if ($this->isTaskWorker()) {
                $this->onSwooleTask($this->server, 0, 0, $task_data);
            } else if ($this->isWorker()) {
                $this->server->task($task_data);
            } else {
                foreach ($current_fds as $fd) {
                    $this->send($fd, $data, true);
                }
            }
        } else {
            foreach ($current_fds as $fd) {
                $this->send($fd, $data, true);
            }
        }
        if ($fromDispatch) return;
        //本机处理不了的发给dispatch
        if ($this->isCluster()) {
            if (count($uids) > 0) {
                ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_sendToUids(array_values($uids), $data);
            }
        }
    }

    /**
     * 获取Topic的数量
     * @param $topic
     */
    public function getSubMembersCountCoroutine($topic)
    {
        return ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->my_getSubMembersCount($topic);
    }

    /**
     * 获取Topic的Member
     * @param $topic
     */
    public function getSubMembersCoroutine($topic)
    {
        return ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->my_getSubMembers($topic);
    }

    /**
     * 获取uid的所有订阅
     * @param $uid
     * @return mixed
     */
    public function getUidTopicsCoroutine($uid)
    {
        return ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->my_getUidTopics($uid);
    }

    /**
     * 添加订阅
     * @param $topic
     * @param $uid
     */
    public function addSub($topic, $uid)
    {
        Utility::CheckTopicFilter($topic);
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_addSub($topic, $uid);
    }

    /**
     * 移除订阅
     * @param $topic
     * @param $uid
     */
    public function removeSub($topic, $uid)
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_removeSub($topic, $uid);
    }

    /**
     * 发布订阅
     * @param $topic
     * @param $data
     * @param array $excludeUids
     */
    public function pub($topic, $data, $excludeUids = [])
    {
        Utility::CheckTopicName($topic);
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_pub($topic, $data, $excludeUids);
    }

    /**
     * 添加AsynPool
     * @param $name
     * @param $pool
     * @throws SwooleException
     */
    public function addAsynPool($name, $pool)
    {
        if (array_key_exists($name, $this->asynPools)) {
            throw  new SwooleException('pool key is exists!');
        }
        $this->asynPools[$name] = $pool;
    }

    /**
     * 获取连接池
     * @param $name
     * @return mixed
     */
    public function getAsynPool($name)
    {
        $pool = $this->asynPools[$name] ?? null;
        return $pool;
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
        $this->initAsynPools($workerId);
        //进程锁保证只有一个进程会执行以下的代码,reload也不会执行
        if (!$this->isTaskWorker() && $this->initLock->trylock()) {
            //进程启动后进行开服的初始化
            $this->onOpenServiceInitialization();
            if (Start::$testUnity) {
                new TestModule(Start::$testUnityDir);
            }
            $this->initLock->lock_read();
        }
        //向SDHelp進程取數據
        if (!$this->isTaskWorker()) {
            $isReload = $this->isReload();
            ConsulHelp::start();
            TimerTask::start();
            if ($this->config->get('catCache.enable', false)) {
                TimerCallBack::init();
                if (!$isReload) {
                    $ready = ProcessManager::getInstance()->getRpcCall(CatCacheProcess::class)->isReady();
                    if (!$ready) {
                        EventDispatcher::getInstance()->addOnceCoroutine(CatCacheProcess::READY);
                    }
                }
                Actor::recovery($workerId);
            }
        }
    }

    /**
     * 初始化各种连接池
     * @param $workerId
     */
    public function initAsynPools($workerId)
    {
        $this->asynPools = [];
        if ($this->config->get('redis.enable', true)) {
            $this->asynPools['redisPool'] = new RedisAsynPool($this->config, $this->config->get('redis.active'));
        }
        if ($this->config->get('mysql.enable', true)) {
            $this->asynPools['mysqlPool'] = new MysqlAsynPool($this->config, $this->config->get('mysql.active'));
        }
        if ($this->config->get('error.dingding_enable', false)) {
            $this->asynPools['dingdingRest'] = new HttpClientPool($this->config, $this->config->get('error.dingding_url'));
        }
        $this->redis_pool = $this->asynPools['redisPool'] ?? null;
        $this->mysql_pool = $this->asynPools['mysqlPool'] ?? null;
    }

    /**
     * 开服初始化(支持协程)
     * @return mixed
     */
    public function onOpenServiceInitialization()
    {
        if ($this->mysql_pool != null) {
            $this->mysql_pool->installDbBuilder();
        }
    }

    /**
     * 连接断开
     * @param $serv
     * @param $fd
     */
    public function onSwooleClose($serv, $fd)
    {
        parent::onSwooleClose($serv, $fd);
        $uid = $this->getUidFromFd($fd);
        $this->unBindUid($uid, $fd);
    }

    /**
     * WS连接断开
     * @param $serv
     * @param $fd
     */
    public function onSwooleWSClose($serv, $fd)
    {
        parent::onSwooleWSClose($serv, $fd);
        $uid = $this->getUidFromFd($fd);
        $this->unBindUid($uid, $fd);
    }

    /**
     * 正常关服操作
     * @param $serv
     */
    public function onSwooleShutdown($serv)
    {
        parent::onSwooleShutdown($serv);
        secho("SYS", "顺利关服");
    }

    /**
     * 是否开启集群
     * @return bool
     */
    public function isCluster()
    {
        return $this->config['consul']['enable'] && $this->config['cluster']['enable'];
    }

    /**
     * @return mixed
     */
    public function isConsul()
    {
        return $this->config['consul']['enable'];
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
            if ($this->isCluster()) {
                ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_kickUid($uid);
            }
        }
    }

    /**
     * 将fd绑定到uid,uid不能为0
     * @param $fd
     * @param $uid
     * @param bool $isKick 是否踢掉uid上一个的链接
     * @throws \Exception
     */
    public function bindUid($fd, $uid, $isKick = true)
    {
        if(!is_string($uid)&&!is_int($uid)){
            throw new \Exception("uid必须为string或者int");
        }
        //这里转换成string型的uid，不然ds/Set有bug
        $uid = (string) $uid;
        if ($isKick) {
            $this->kickUid($uid, false);
        }
        $this->uid_fd_table->set($uid, ['fd' => $fd]);
        $this->fd_uid_table->set($fd, ['uid' => $uid]);
        if ($this->isCluster()) {
            ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_addUid($uid);
        } else {
            get_instance()->pub('$SYS/uidcount', count($this->uid_fd_table));
        }
    }

    /**
     * 解绑uid，链接断开自动解绑
     * @param $uid
     * @param $fd
     */
    public function unBindUid($uid, $fd)
    {
        //更新共享内存
        $this->uid_fd_table->del($uid);
        $this->fd_uid_table->del($fd);
        //这里无论是不是集群都需要调用
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_removeUid($uid);
        if (!$this->isCluster()) {
            get_instance()->pub('$SYS/uidcount', count($this->uid_fd_table));
        }
    }

    /**
     * uid是否在线(协程)
     * @param $uid
     * @return int
     * @throws SwooleException
     */
    public function coroutineUidIsOnline($uid)
    {
        if ($this->isCluster()) {
            return ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->isOnline($uid);
        } else {
            return $this->uid_fd_table->exist($uid);
        }
    }


    /**
     * 获取在线人数(协程)
     * @return int
     * @throws SwooleException
     */
    public function coroutineCountOnline()
    {
        if ($this->isCluster()) {
            return ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->countOnline();
        } else {
            return count($this->uid_fd_table);
        }
    }

    /**
     * 获取所有在线uid
     * @return int
     * @throws SwooleException
     */
    public function coroutineGetAllUids()
    {
        if ($this->isCluster()) {
            return ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->getAllUids();
        } else {
            $uids = [];
            foreach ($this->uid_fd_table as $key => $value) {
                $uids[] = $key;
            }
            return $uids;
        }
    }

    /**
     * 向task发送强制停止命令
     * @param $task_id
     */
    public function stopTask($task_id)
    {
        $task_pid = get_instance()->tid_pid_table->get($task_id)['pid'];
        if ($task_pid != null) {
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
            $row['task_id'] = $id;
            $row['run_time'] = time() - $row['start_time'];
            $tasks[] = $row;
        }
        return $tasks;
    }

    /**
     * 获取本机ip
     * @return string
     */
    public function getBindIp()
    {
        if (empty($this->bind_ip)) {
            $this->bind_ip = $this->config['consul']['bind_addr'] ?? null;
            if ($this->bind_ip == null) {
                $this->bind_ip = getServerIp($this->config['consul']['bind_net_dev'] ?? 'eth0');
            }
        }
        return $this->bind_ip;
    }

    /**
     * @var int
     */
    protected $lastTime = 0;

    /**
     * @var int
     */
    protected $lastReqTimes = 0;

    /**
     * 获得服务器状态
     * @return mixed
     */
    public function getStatus()
    {
        $status = get_instance()->server->stats();
        $now_time = getMillisecond();
        $exTime = $now_time - $this->lastTime;
        if ($exTime == 0) {
            $qps = 0;
        } else {
            $qps = (int)(($status['request_count'] - $this->lastReqTimes) / $exTime * 1000);
        }
        $this->lastTime = $now_time;
        $this->lastReqTimes = $status['request_count'];
        $status['isDebug'] = Start::getDebug();
        $status['isLeader'] = Start::isLeader();
        $status['qps'] = $qps;
        $status['system'] = PHP_OS;
        $status['Swoole_version'] = SWOOLE_VERSION;
        $status['SD_version'] = SwooleServer::version;
        $status['PHP_version'] = PHP_VERSION;
        $status['worker_num'] = $this->worker_num;
        $status['task_num'] = $this->task_num;
        $status['max_connection'] = $this->max_connection;
        $status['start_time'] = Start::getStartTime();
        $status['run_time'] = format_date(strtotime(date('Y-m-d H:i:s')) - strtotime(Start::getStartTime()));
        $poolStatus = $this->helpGetAllStatus();
        $status['coroutine_num'] = $poolStatus['coroutine_num'];
        $status['pool'] = $poolStatus['pool'];
        $status['model_pool'] = $poolStatus['model_pool'];
        $status['controller_pool'] = $poolStatus['controller_pool'];
        $status['ports'] = $this->portManager->getPortStatus();
        $data = ProcessManager::getInstance()->getRpcCall(SDHelpProcess::class)->getData(ConsulHelp::DISPATCH_KEY);
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = json_decode($value, true);
                foreach ($data[$key] as &$one) {
                    $one = $one['Service'];
                }
            }
        }
        $status['consul_services'] = $data;
        $this->pub('$SYS/' . getNodeName() . '/status', $status);
    }

    /**
     * @return mixed
     */
    public function getPoolStatus()
    {
        $status['pool'] = Pool::getInstance()->getStatus();
        $status['model_pool'] = ModelFactory::getInstance()->getStatus();
        $status['controller_pool'] = ControllerFactory::getInstance()->getStatus();
        $status['coroutine_num'] = 0;
        return $status;
    }

    /**
     * @return array
     */
    protected function helpGetAllStatus()
    {
        $status = ['pool' => [], 'model_pool' => [], 'controller_pool' => [], 'coroutine_num' => 0];
        for ($i = 0; $i < $this->worker_num; $i++) {
            $result = ProcessManager::getInstance()->getRpcCallWorker(self::get_instance()->workerId)->getPoolStatus();
            if (empty($result)) return;
            $this->helpMerge($status['pool'], $result['pool']);
            $this->helpMerge($status['model_pool'], $result['model_pool']);
            $this->helpMerge($status['controller_pool'], $result['controller_pool']);
            $status['coroutine_num'] += $result['coroutine_num'];
        }
        return $status;
    }

    /**
     * @param $a1
     * @param $a2
     * @return mixed
     */
    protected function helpMerge(&$a1, $a2)
    {
        foreach ($a2 as $key => $value) {
            if (array_key_exists($key, $a1)) {
                $a1[$key] += $a2[$key];
            } else {
                $a1[$key] = $a2[$key];
            }
        }
        return $a1;
    }

    /**
     * 发布uid信息
     * @param $uid
     * @return mixed|null
     */
    public function getUidInfo($uid)
    {
        $fd = $this->getFdFromUid($uid);
        if (empty($fd)) {
            if (!$this->isCluster()) {
                return [];
            } else {
                $fdInfo = ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->my_getUidInfo($uid);
                return $fdInfo;
            }
        } else {
            $fdInfo = $this->getFdInfo($fd);
            $fdInfo['node'] = getNodeName();
            return $fdInfo;
        }
    }
}