<?php
namespace Server;

use Noodlehaus\Exception;
use Server\Client\Client;
use Server\CoreBase\InotifyProcess;
use Server\CoreBase\SwooleException;
use Server\DataBase\AsynPoolManager;
use Server\DataBase\MysqlAsynPool;
use Server\DataBase\RedisAsynPool;
use Server\DataBase\RedisCoroutine;

define("SERVER_DIR", __DIR__);
define("APP_DIR", __DIR__ . "/../app");
define("WWW_DIR", __DIR__ . "/../www");

/**
 * Created by PhpStorm.
 * User: tmtbe
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
     * 分布式系统服务器唯一标识符
     * @var int
     */
    private $USID;
    /**
     * dispatch fd
     * @var array
     */
    protected $dispatchClientFds = [];
    /**
     * @var \Redis
     */
    public $redis_client;
    /**
     * @var RedisAsynPool
     */
    public $redis_pool;
    /**
     * @var MysqlAsynPool
     */
    public $mysql_pool;
    /**
     * @var AsynPoolManager
     */
    private $asnyPoolManager;
    /**
     * 多少人启用task进行发送
     * @var
     */
    private $send_use_task_num;

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
     * 各种client
     * @var Client
     */
    public $client;
    /**
     * 覆盖set配置
     * @var array
     */
    public $overrideSetConfig = [];

    public $cache404;

    /**
     * SwooleDistributedServer constructor.
     */
    public function __construct()
    {
        self::$instance =& $this;
        $this->name = self::SERVER_NAME;
        parent::__construct();
        $this->clearState();
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
            $this->getRedis()->del(SwooleMarco::redis_uid_usid_hash_name);
            $this->getRedis()->close();
            unset($this->redis_client);
        }
    }

    /**
     * 设置配置
     */
    public function setConfig()
    {
        $this->http_socket_name = $this->config['http_server']['socket'];
        $this->http_port = $this->config['http_server']['port'];
        $this->socket_type = SWOOLE_SOCK_TCP;
        $this->socket_name = $this->config['server']['socket'];
        $this->port = $this->config['server']['port'];
        $this->user = $this->config->get('server.set.user', '');
        $this->send_use_task_num = $this->config['server']['send_use_task_num'];
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
     * 开始前创建共享内存保存USID值
     */
    public function beforeSwooleStart()
    {
        //创建uid->fd共享内存表
        $this->uid_fd_table = new \swoole_table(65536);
        $this->uid_fd_table->column('fd', \swoole_table::TYPE_INT, 8);
        $this->uid_fd_table->create();
        //创建redis，mysql异步连接池进程
        if ($this->config->get('asyn_process_enable', false)) {//代表启动单独进程进行管理
            $this->pool_process = new \swoole_process(function ($process) {
                $process->name('SWD-ASYN');
                $this->asnyPoolManager = new AsynPoolManager($process, $this);
                $this->asnyPoolManager->event_add();
                $this->asnyPoolManager->registAsyn(new RedisAsynPool());
                $this->asnyPoolManager->registAsyn(new MysqlAsynPool());
            }, false, 2);
            $this->server->addProcess($this->pool_process);
        }
        //reload监控进程
        if ($this->config->get('auto_reload_enable', false)) {//代表启动单独进程进行reload管理
            $reload_process = new \swoole_process(function ($process) {
                $process->name('SWD-RELOAD');
                new InotifyProcess($this->server);
            }, false, 2);
            $this->server->addProcess($reload_process);
        }
        if ($this->config->get('use_dispatch')) {
            //创建dispatch端口用于连接dispatch
            $this->dispatch_port = $this->server->listen($this->config['server']['socket'], $this->config['server']['dispatch_port'], SWOOLE_SOCK_TCP);
            $this->dispatch_port->set($this->setServerSet());
            $this->dispatch_port->on('close', function ($serv, $fd) {
                print_r("Remove a dispatcher.\n");
                for ($i = 0; $i < $this->worker_num + $this->task_num; $i++) {
                    if ($i == $serv->worker_id) continue;
                    $data = $this->packSerevrMessageBody(SwooleMarco::REMOVE_DISPATCH_CLIENT, $fd);
                    $serv->sendMessage($data, $i);
                }
                $this->removeDispatch($fd);
            });
            $this->dispatch_port->on('receive', function ($serv, $fd, $from_id, $data) {
                $data = substr($data, SwooleMarco::HEADER_LENGTH);
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
                        $send_data = $this->packSerevrMessageBody($type, $uns_data);
                        for ($i = 0; $i < $this->worker_num + $this->task_num; $i++) {
                            if ($i == $serv->worker_id) continue;
                            $serv->sendMessage($send_data, $i);
                        }
                        $this->addDispatch($uns_data);
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
                }
            });
        }
    }

    /**
     * 添加一个dispatch
     * @param $data
     */
    public function addDispatch($data)
    {
        $this->USID = $data['usid'];
        $this->dispatchClientFds[$data['fd']] = $data['fd'];
    }

    /**
     * 移除dispatch
     * @param $data
     */
    public function removeDispatch($fd)
    {
        unset($this->dispatchClientFds[$fd]);
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
        switch ($data['type']) {
            case SwooleMarco::MSG_TYPE_USID:
                $this->addDispatch($data['message']);
                break;
            case SwooleMarco::REMOVE_DISPATCH_CLIENT:
                $this->removeDispatch($data['message']);
                break;
            case SwooleMarco::MSG_TYPE_REDIS_MESSAGE:
                $this->asnyPoolManager->distribute($data['message']);
                break;
        }
    }

    /**
     * 随机选择一个dispatch发送消息
     * @param $data
     */
    private function sendToDispatchMessage($type, $data)
    {
        $send_data = $this->packSerevrMessageBody($type, $data);
        $fd = $this->dispatchClientFds[array_rand($this->dispatchClientFds)];
        if ($fd != null) {
            $this->server->send($fd, $this->encode($send_data));
        } else {
            $this->server->task($send_data);
        }
    }

    /**
     * task异步任务
     * @param $serv
     * @param $task_id
     * @param $from_id
     * @param $data
     * @return mixed
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
                    $serv->send($fd, $message['data']);
                }
                return null;
            case SwooleMarco::MSG_TYPE_SEND_GROUP://群组
                $uids = $this->redis_client->hGetAll(SwooleMarco::redis_group_hash_name_prefix . $message['groupId']);
                foreach ($uids as $uid) {
                    if ($this->uid_fd_table->exist($uid)) {
                        $fd = $this->uid_fd_table->get($uid)['fd'];
                        $this->send($fd, $message['data']);
                    }
                }
                return null;
            case SwooleMarco::SERVER_TYPE_TASK://task任务
                $task_name = $message['task_name'];
                $task = $this->loader->task($task_name);
                $task_fuc_name = $message['task_fuc_name'];
                $task_data = $message['task_fuc_data'];
                if (method_exists($task, $task_fuc_name)) {
                    $result = call_user_func_array(array($task, $task_fuc_name), $task_data);
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
     * 重写onSwooleWorkerStart方法，添加异步redis
     * @param $serv
     * @param $workerId
     */
    public function onSwooleWorkerStart($serv, $workerId)
    {
        if (!$serv->taskworker) {
            //异步redis连接池
            $this->redis_pool = new RedisAsynPool();
            $this->redis_pool->worker_init($workerId);
            //异步mysql连接池
            $this->mysql_pool = new MysqlAsynPool();
            $this->mysql_pool->worker_init($workerId);
            //注册
            $this->asnyPoolManager = new AsynPoolManager($this->pool_process, $this);
            if (!$this->config['asyn_process_enable']) {
                $this->asnyPoolManager->no_event_add();
            }
            $this->asnyPoolManager->registAsyn($this->redis_pool);
            $this->asnyPoolManager->registAsyn($this->mysql_pool);
            //初始化异步Client
            $this->client = new Client();
        }
        //定时器
        if ($workerId == $this->worker_num - 1) {//最后一个worker处理启动定时器
            //重新读入timerTask配置
            $timerTaskConfig = $this->config->load(__DIR__ . '/../config/timerTask.php');
            $timer_tasks = $timerTaskConfig->get('timerTask');
            $timer_tasks_used = array();

            foreach ($timer_tasks as $timer_task) {
                $task_name = $timer_task['task_name'];
                $method_name = $timer_task['method_name'];
                if (!key_exists('start_time', $timer_task)) {
                    $start_time = -1;
                } else {
                    $start_time = strtotime(date($timer_task['start_time']));
                }
                if (!key_exists('end_time', $timer_task)) {
                    $end_time = -1;
                } else {
                    $end_time = strtotime(date($timer_task['end_time']));
                }
                $interval_time = $timer_task['interval_time'] < 1 ? 1 : $timer_task['interval_time'];
                $max_exec = $timer_task['max_exec']??-1;
                $timer_tasks_used[] = [
                    'task_name' => $task_name,
                    'method_name' => $method_name,
                    'start_time' => $start_time,
                    'end_time' => $end_time,
                    'interval_time' => $interval_time,
                    'max_exec' => $max_exec,
                    'now_exec' => 0
                ];
            }
            if (count($timer_tasks_used) > 0) {
                $serv->tick(1000, function () use (&$timer_tasks_used) {
                    $time = time();
                    foreach ($timer_tasks_used as &$timer_task) {
                        if ($timer_task['start_time'] < $time && $timer_task['start_time'] != -1) {
                            $count = round(($time - $timer_task['start_time']) / $timer_task['interval_time']);
                            $timer_task['start_time'] += $count * $timer_task['interval_time'];
                        }
                        if (($time == $timer_task['start_time'] || $timer_task['start_time'] == -1) &&
                            ($time < $timer_task['end_time'] || $timer_task['end_time'] = -1) &&
                            ($timer_task['now_exec'] < $timer_task['max_exec'] || $timer_task['max_exec'] == -1)
                        ) {
                            $timer_task['now_exec']++;
                            if ($timer_task['start_time'] == -1) $timer_task['start_time'] = $time;
                            $timer_task['start_time'] += $timer_task['interval_time'];
                            $task = $this->loader->task($timer_task['task_name']);
                            call_user_func([$task, $timer_task['method_name']]);
                            $task->startTask(null);
                        }
                    }
                });
            }
        }
        parent::onSwooleWorkerStart($serv, $workerId);
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
            $generator = $this->onUidCloseClear($uid);
            if ($generator instanceof \Generator) {
                $this->coroutine->start($generator);
            }
            $this->unBindUid($uid);
        }
        parent::onSwooleClose($serv, $fd);
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
     */
    public function bindUid($fd, $uid)
    {
        //将这个fd与当前worker进行绑定
        $this->server->bind($fd, $uid);
        //建立映射表
        if (isset($this->redis_client)) {
            $this->redis_client->hSet(SwooleMarco::redis_uid_usid_hash_name, $uid, $this->USID);
        } else {
            $this->redis_pool->hSet(SwooleMarco::redis_uid_usid_hash_name, $uid, $this->USID, null);
        }
        //加入共享内存
        $this->uid_fd_table->set($uid, ['fd' => $fd]);
    }

    /**
     * 解绑uid，链接断开自动解绑
     * @param $uid
     */
    public function unBindUid($uid)
    {
        //更新映射表
        if (isset($this->redis_client)) {
            $this->redis_client->hDel(SwooleMarco::redis_uid_usid_hash_name, $uid);
        } else {
            $this->redis_pool->hDel(SwooleMarco::redis_uid_usid_hash_name, $uid, null);
        }
        //更新共享内存
        $this->uid_fd_table->del($uid);
    }

    /**
     * uid是否在线(异步时候需要提供callback,task可以直接返回结果)
     * @param $uid
     * @param $callback
     * @return int 0代表不在线1代表在线
     */
    public function uidIsOnline($uid, $callback)
    {
        if ($this->redis_client) {
            return $this->redis_client->hExists(SwooleMarco::redis_uid_usid_hash_name, $uid);
        } else {
            $this->redis_pool->hExists(SwooleMarco::redis_uid_usid_hash_name, $uid, $callback);
        }
    }

    /**
     * 协程版uid是否在线
     * @param $uid
     * @return RedisCoroutine
     */
    public function coroutineUidIsOnline($uid)
    {
        return $this->redis_pool->coroutineSend('hExists', SwooleMarco::redis_uid_usid_hash_name, $uid);
    }

    /**
     * 获取在线人数(异步时候需要提供callback,task可以直接返回结果)
     * @param $callback
     * @return int
     */
    public function countOnline($callback)
    {
        if (isset($this->redis_client)) {
            return $this->redis_client->hLen(SwooleMarco::redis_uid_usid_hash_name);
        } else {
            $this->redis_pool->hLen(SwooleMarco::redis_uid_usid_hash_name, $callback);
        }
    }

    /**
     * 协程版获取在线人数
     * @return RedisCoroutine
     */
    public function coroutineCountOnline()
    {
        return $this->redis_pool->coroutineSend('hLen', SwooleMarco::redis_uid_usid_hash_name);
    }

    /**
     * 添加到群
     * @param $uid int
     * @param $group_id int
     */
    public function addToGroup($uid, $group_id)
    {
        if (isset($this->redis_client)) {
            $this->redis_client->hSet(SwooleMarco::redis_group_hash_name_prefix . $group_id, $uid, $uid);
        } else {
            $this->redis_pool->hSet(SwooleMarco::redis_group_hash_name_prefix . $group_id, $uid, $uid, null);
        }
    }

    /**
     * 从群里移除
     * @param $uid
     * @param $group_id
     */
    public function removeFromGroup($uid, $group_id)
    {
        if (isset($this->redis_client)) {
            $this->redis_client->hDel(SwooleMarco::redis_group_hash_name_prefix . $group_id, $uid);
        } else {
            $this->redis_client->hDel(SwooleMarco::redis_group_hash_name_prefix . $group_id, $uid, null);
        }
    }

    /**
     * 删除群
     * @param $group_id
     */
    public function delGroup($group_id)
    {
        if (isset($this->redis_client)) {
            $this->redis_client->del(SwooleMarco::redis_group_hash_name_prefix . $group_id);
        } else {
            $this->redis_client->del(SwooleMarco::redis_group_hash_name_prefix . $group_id, null);
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
            $this->server->task($task_data);
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
     * 获取同步redis
     * @return \Redis
     * @throws SwooleException
     */
    public function getRedis()
    {
        if (isset($this->redis_client)) return $this->redis_client;
        //同步redis连接，给task使用
        $this->redis_client = new \Redis();

        if ($this->redis_client->connect($this->config['redis']['ip'], $this->config['redis']['port']) == false) {
            throw new SwooleException($this->redis_client->getLastError());
        }
        if ($this->config->has('redis.password')) {//存在验证
            if ($this->redis_client->auth($this->config['redis']['password']) == false) {
                throw new SwooleException($this->redis_client->getLastError());
            }
        }
        $this->redis_client->select($this->config['redis']['select']);
        return $this->redis_client;
    }

    /**
     * 获取实例
     * @return SwooleDistributedServer
     */
    public static function &get_instance()
    {
        return self::$instance;
    }
}