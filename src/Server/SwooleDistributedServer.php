<?php

namespace Server;

use Server\Asyn\Mysql\Miner;
use Server\Asyn\Mysql\MysqlAsynPool;
use Server\Asyn\Redis\RedisAsynPool;
use Server\Asyn\Redis\RedisLuaManager;
use Server\Components\Cluster\ClusterHelp;
use Server\Components\Cluster\ClusterProcess;
use Server\Components\Consul\ConsulHelp;
use Server\Components\Consul\ConsulProcess;
use Server\Components\Event\EventDispatcher;
use Server\Components\GrayLog\GrayLogHelp;
use Server\Components\Process\ProcessManager;
use Server\Components\SDHelp\SDHelpProcess;
use Server\Components\TimerTask\TimerTask;
use Server\CoreBase\SwooleException;
use Server\Coroutine\Coroutine;
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
     * @var bool
     */
    private $debug = false;
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
     * 设置Debug模式
     */
    public function setDebugMode()
    {
        $this->debug = true;
    }

    /**
     * 是否是Debug
     * @return mixed
     */
    public function isDebug()
    {
        return $this->debug;
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
        return parent::start();
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
        //创建uid->fd共享内存表
        $this->createUidTable();
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
        //init锁
        $this->initLock = new \swoole_lock(SWOOLE_RWLOCK);
    }

    /**
     * 创建uid->fd共享内存表
     */
    protected function createUidTable()
    {
        $this->uid_fd_table = new \swoole_table(65536);
        $this->uid_fd_table->column('fd', \swoole_table::TYPE_INT, 8);
        $this->uid_fd_table->create();
    }

    /**
     * 创建用户进程
     */
    public function startProcess()
    {
        //timerTask,reload进程
        ProcessManager::getInstance()->addProcess(SDHelpProcess::class, false);
        //consul进程
        if ($this->config->get('consul.enable', false)) {
            ProcessManager::getInstance()->addProcess(ConsulProcess::class, false);
        }
        //Cluster进程
        ProcessManager::getInstance()->addProcess(ClusterProcess::class, false);
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
     * 发送给所有的异步进程，$callStaticFuc为静态方法,会在每个进程都执行
     * @param $type
     * @param $uns_data
     * @param string $callStaticFuc
     */
    public function sendToAllAsynWorks($type, $uns_data, string $callStaticFuc)
    {
        $send_data = $this->packSerevrMessageBody($type, $uns_data, $callStaticFuc);
        for ($i = 0; $i < $this->worker_num; $i++) {
            if ($this->server->worker_id == $i) continue;
            $this->server->sendMessage($send_data, $i);
        }
        //自己的进程是收不到消息的所以这里执行下
        call_user_func($callStaticFuc, $uns_data);
    }

    /**
     * 发送给随机进程
     * @param $type
     * @param $uns_data
     * @param string $callStaticFuc
     */
    public function sendToRandomWorker($type, $uns_data, string $callStaticFuc)
    {
        $send_data = get_instance()->packSerevrMessageBody($type, $uns_data, $callStaticFuc);
        $id = rand(0, get_instance()->worker_num - 1);
        if ($this->server->worker_id == $id) {
            //自己的进程是收不到消息的所以这里执行下
            call_user_func($callStaticFuc, $uns_data);
        } else {
            get_instance()->sendMessage($send_data, $id);
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
            case SwooleMarco::SERVER_TYPE_TASK://task任务
                $task_name = $message['task_name'];
                $task = $this->loader->task($task_name, $this);
                $task_fuc_name = $message['task_fuc_name'];
                $task_data = $message['task_fuc_data'];
                $task_context = $message['task_context'];
                $call = [$task, $task_fuc_name];
                if (is_callable($call)) {
                    //给task做初始化操作
                    $task->initialization($task_id, $from_id, $this->server->worker_pid, $task_name, $task_fuc_name, $task_context);
                    $result = Coroutine::startCoroutine($call, $task_data);
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
     * @param $fd
     * @return mixed
     */
    public function getFdInfo($fd)
    {
        $fdinfo = $this->server->connection_info($fd);
        return $fdinfo;
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
     * 广播
     * @param $data
     * @param bool $fromDispatch
     */
    public function sendToAll($data, $fromDispatch = false)
    {
        $send_data = $this->packSerevrMessageBody(SwooleMarco::MSG_TYPE_SEND_ALL, ['data' => $data]);
        if ($this->isTaskWorker()) {
            $this->onSwooleTask($this->server, 0, 0, $send_data);
        } else {
            $this->server->task($send_data);
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
        if (count($current_fds) > $this->send_use_task_num) {//过多人就通过task
            $task_data = $this->packSerevrMessageBody(SwooleMarco::MSG_TYPE_SEND_BATCH, ['data' => $data, 'fd' => $current_fds]);
            if ($this->isTaskWorker()) {
                $this->onSwooleTask($this->server, 0, 0, $task_data);
            } else {
                $this->server->task($task_data);
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
     * 添加订阅
     * @param $sub
     * @param $uid
     */
    public function addSub($sub, $uid)
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_addSub($sub, $uid);
    }

    /**
     * 移除订阅
     * @param $sub
     * @param $uid
     */
    public function removeSub($sub, $uid)
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_removeSub($sub, $uid);
    }

    /**
     * 发布订阅
     * @param $sub
     * @param $data
     */
    public function pub($sub, $data)
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_pub($sub, $data);
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
        switch ($message['type']) {
            case SwooleMarco::PROCESS_RPC:
                EventDispatcher::getInstance()->dispatch($message['message']['token'], $message['message']['result'], true);
                break;
            default:
                if (!empty($message['func'])) {
                    call_user_func($message['func'], $message['message']);
                }
        }
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
        $this->initAsynPools($workerId);
        $this->redis_pool = $this->asynPools['redisPool'] ?? null;
        $this->mysql_pool = $this->asynPools['mysqlPool'] ?? null;
        //进程锁保证只有一个进程会执行以下的代码,reload也不会执行
        if (!$this->isTaskWorker() && $this->initLock->trylock()) {
            //进程启动后进行开服的初始化
            Coroutine::startCoroutine([$this, 'onOpenServiceInitialization']);
            if (Start::$testUnity) {
                new TestModule(Start::$testUnityDir);
            }
            $this->initLock->lock_read();
        }
        //向SDHelp進程取數據
        if (!$this->isTaskWorker()) {
            ConsulHelp::start();
            TimerTask::start();
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
    }

    /**
     * 开服初始化(支持协程)
     * @return mixed
     */
    public function onOpenServiceInitialization()
    {

    }

    /**
     * 连接断开
     * @param $serv
     * @param $fd
     */
    public function onSwooleClose($serv, $fd)
    {
        $info = $serv->connection_info($fd, 0, true);
        $uid = $info['uid'] ?? 0;
        if (!empty($uid)) {
            Coroutine::startCoroutine([$this, 'onUidCloseClear'], [$uid]);
            $this->unBindUid($uid, $fd);
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
        $this->onSwooleClose($serv, $fd);
    }

    /**
     * 解绑uid，链接断开自动解绑
     * @param $uid
     * @param $fd
     */
    public function unBindUid($uid, $fd)
    {
        //更新共享内存
        if ($this->uid_fd_table->get($uid, 'fd') == $fd) {
            $this->uid_fd_table->del($uid);
        }
        //这里无论是不是集群都需要调用
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_removeUid($uid);
    }

    /**
     * 当一个绑定uid的连接close后的清理
     * 支持协程
     * @param $uid
     */
    abstract public function onUidCloseClear($uid);

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
     * 将fd绑定到uid,uid不能为0
     * @param $fd
     * @param $uid
     * @param bool $isKick 是否踢掉uid上一个的链接
     */
    public function bindUid($fd, $uid, $isKick = true)
    {
        $uid = (int)$uid;
        if ($isKick) {
            $this->kickUid($uid, false);
        }
        //将这个fd与当前worker进行绑定
        $this->server->bind($fd, $uid);
        if ($this->isCluster()) {
            ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_addUid($uid);
        }
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
        if ($this->isCluster()) {
            return yield ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->isOnline($uid);
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
            return yield ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->countOnline();
        } else {
            return count($this->uid_fd_table);
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
}