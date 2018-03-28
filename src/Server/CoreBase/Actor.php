<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-1-2
 * Time: 下午1:47
 */

namespace Server\CoreBase;


use Server\Asyn\Mysql\Miner;
use Server\Components\CatCache\CatCacheRpcProxy;
use Server\Components\Cluster\ClusterProcess;
use Server\Components\Event\EventCoroutine;
use Server\Components\Event\EventDispatcher;
use Server\Components\Process\ProcessManager;
use Server\Memory\Pool;

abstract class Actor extends CoreBase
{
    protected static $RPCtoken = 0;
    const SAVE_NAME = "@Actor";
    const ALL_COMMAND = "@All_Command";
    /**
     * @var string
     */
    protected $name;
    /**
     * 接收消息的id
     * @var string
     */
    protected $messageId;
    /**
     * @var array
     */
    private $timerIdArr = [];

    /**
     * @var ActorContext
     */
    protected $saveContext;
    /**
     * 事务id
     * @var int
     */
    protected $beginId = 0;
    /**
     * 当前事务ID
     * @var int
     */
    protected $nowAffairId = 0;
    /**
     * 邮箱
     * @var array
     */
    protected $mailbox = [];

    /**
     * @var Miner
     */
    protected $db;

    /**
     * @param $name
     * @param $saveContext 用于恢复状态
     * @throws \Exception
     */
    public function initialization($name, $saveContext = null)
    {
        $result = ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->my_addActor($name);
        //恢复的时候不需要判断重名问题，只有在创建的时候才需要
        if (!$result && $saveContext == null) {
            throw new \Exception("Actor不允许重名");
        }
        $this->name = $name;
        if ($saveContext == null) {
            $delimiter = $this->config->get("catCache.delimiter", ".");
            $path = self::SAVE_NAME . $delimiter . $name;
            $this->saveContext = Pool::getInstance()->get(ActorContext::class);
            $this->saveContext->initialization($path, get_class($this), get_instance()->getWorkerId());
        } else {
            $this->saveContext = $saveContext;
        }
        $this->messageId = self::SAVE_NAME . $name;
        //接收自己的消息
        EventDispatcher::getInstance()->add($this->messageId, function ($event) {
            $this->handle($event->data);
        });
        //接收管理器统一派发的消息
        EventDispatcher::getInstance()->add(self::SAVE_NAME . Actor::ALL_COMMAND, function ($event) {
            $this->handle($event->data);
        });
        $this->saveContext->save();
        $this->installMysqlPool($this->mysql_pool);
        $this->db = $this->mysql_pool->dbQueryBuilder;
        $this->execRegistHandle();
    }

    /**
     * 处理注册状态
     * @param $key
     * @param $value
     */
    abstract public function registStatusHandle($key, $value);

    /**
     * 恢复状态执行注册语句
     */
    private function execRegistHandle()
    {
        if ($this->saveContext['@status'] == null) return;
        foreach ($this->saveContext['@status'] as $key => $value) {
            $this->registStatusHandle($key, $value);
        }
    }

    /**
     * 注册状态
     * @param $key
     * @param $value
     * @return mixed
     * @throws \Exception
     */
    protected function setStatus($key, $value)
    {
        if (is_array($value) || is_object($value)) {
            throw new \Exception("状态机只能为简单数据");
        }
        if ($this->saveContext["@status"] == null) {
            $this->saveContext["@status"] = [$key => $value];
        } else {
            //如果存在了并且相等就pass
            if (isset($this->saveContext["@status"][$key]) && $this->saveContext["@status"][$key] == $value) {
                return;
            }
            $this->saveContext->getData()["@status"][$key] = $value;
            //二维数组需要自己手动调用save
            $this->saveContext->save();
        }

        $this->registStatusHandle($key, $value);
    }

    /**
     * 处理函数
     * @param $data
     */
    protected function handle($data)
    {
        $function = $data['call'];
        $params = $data['params'] ?? [];
        $token = $data['token'];
        $workerId = $data['worker_id'];
        $oneWay = $data['oneWay'];
        $node = $data['node'];
        $bindId = $data['bindId'];
        if (!empty($this->nowAffairId)) {//代表有事务
            if ($bindId != $this->nowAffairId || empty($bindId)) {//不是当前的事务，或者不是事务就放进邮箱中
                array_push($this->mailbox, $data);
                return;
            }
        }
        try {
            $generator = \co::call_user_func_array([$this, $function], $params);
        } catch (\Throwable $e) {
            $generator = new RPCThrowable($e);
        }
        if (!$oneWay) {
            $this->rpcBack($workerId, $token, $generator, $node);
        }
    }

    /**
     * 开启事务
     * @return int
     */
    public function begin()
    {
        $this->beginId++;
        $this->nowAffairId = $this->beginId;
        return $this->beginId;
    }

    /**
     * 结束事务
     * @param $beginId
     * @return bool
     */
    public function end($beginId)
    {
        if ($this->nowAffairId == $beginId) {
            $this->nowAffairId = 0;
        } else {
            return false;
        }
        //邮箱中有消息
        if (count($this->mailbox) > 0) {
            while (true) {
                if (count($this->mailbox) == 0) break;
                $data = array_shift($this->mailbox);
                $this->handle($data);
                if ($data['call'] == "begin") {
                    break;
                }
            }
        }
        return true;
    }

    /**
     * @param $workerId
     * @param $token
     * @param $result
     * @param $node
     */
    protected function rpcBack($workerId, $token, $result, $node)
    {
        if (!get_instance()->isCluster() || $node == getNodeName()) {//非集群或者就是自己机器直接发
            EventDispatcher::getInstance()->dispathToWorkerId($workerId, $token, $result);
        } else {
            ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->callActorBack($workerId, $token, $result, $node);
        }
    }

    /**
     * 创建附属actor
     * @param $class
     * @return mixed
     */
    protected function createActor($class, $name)
    {
        $actor = Pool::getInstance()->get($class);
        $actor->initialization($name);
        $this->addChild($actor);
        return $actor;
    }

    /**
     * 恢复注册
     * recoveryRegister
     */
    public function recoveryRegister()
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->recoveryRegisterActor($this->name);
    }

    public function destroy()
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->my_removeActor($this->name);
        EventDispatcher::getInstance()->removeAll($this->messageId);
        EventDispatcher::getInstance()->remove(self::SAVE_NAME . Actor::ALL_COMMAND, [$this, '_handle']);
        foreach ($this->timerIdArr as $id) {
            @\swoole_timer_clear($id);
        }
        $this->saveContext->destroy();
        Pool::getInstance()->push($this->saveContext);
        $this->saveContext = null;
        $this->timerIdArr = [];
        $this->beginId = 0;
        $this->nowAffairId = 0;
        parent::destroy();
    }

    /**
     * @return string
     */
    public function getMessageId()
    {
        return $this->messageId;
    }

    /**
     * 定时器tick
     * @param $ms
     * @param $callback
     * @param $user_param
     */
    public function tick($ms, $callback, $user_param = null)
    {
        $id = \swoole_timer_tick($ms, function ($user_param_one) use ($callback) {
            try {
                $callback($user_param_one);
            }catch (\Throwable $e){
                displayExceptionHandler($e);
            }
        }, $user_param);
        $this->timerIdArr[$id] = $id;
        return $id;
    }

    /**
     * 定时器after
     * @param $ms
     * @param $callback
     * @param $user_param
     */
    public function after($ms, $callback, $user_param = null)
    {
        $id = \swoole_timer_after($ms, function ($user_param_one) use ($callback) {
            go(function () use ($callback, $user_param_one) {
                try {
                    $callback($user_param_one);
                }catch (\Throwable $e){
                    displayExceptionHandler($e);
                }
            });
        }, $user_param);
        $this->timerIdArr[$id] = $id;
        return $id;
    }

    /**
     * 清除
     * @param $id
     */
    public function clearTimer($id)
    {
        @\swoole_timer_clear($id);
        unset($this->timerIdArr[$id]);
    }

    /**
     * @param $actorName
     * @return ActorRpc
     */
    public static function getRpc($actorName)
    {
        return Pool::getInstance()->get(ActorRpc::class)->init($actorName);
    }

    /**
     * 呼叫Actor
     * @param $actorName
     * @param $call
     * @param $params
     * @param bool $oneWay
     * @param null $bindId
     * @param callable|null $set
     * @return EventCoroutine
     */
    public static function call($actorName, $call, $params = null, $oneWay = false, $bindId = null, callable $set = null)
    {
        $data['call'] = $call;
        $data['params'] = $params;
        $data['bindId'] = $bindId;
        $data['oneWay'] = $oneWay;
        $data['node'] = getNodeName();
        $data['worker_id'] = get_instance()->getWorkerId();
        self::$RPCtoken++;
        $data['token'] = '[Actor]'."[$actorName::$call]"."[".get_instance()->getWorkerId()."]" . "[".self::$RPCtoken."]";
        $result = null;
        if (!$oneWay) {
            $result = Pool::getInstance()->get(EventCoroutine::class)->init($data['token'], function (EventCoroutine $eventCoroutine) use ($set) {
                $eventCoroutine->setDelayRecv();
                if ($set != null) {
                    $set($eventCoroutine);
                }
            });
        }
        if (get_instance()->isCluster()) {//集群通过ClusterProcess进行分发
            ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->callActor($actorName, $data);
        } else {//非集群直接发
            EventDispatcher::getInstance()->dispatch(self::SAVE_NAME . $actorName, $data);
        }
        if ($oneWay) {
            return null;
        } else {
            return $result->recv();
        }
    }

    /**
     * 创建Actor
     * @param $class
     * @param $name
     * @return mixed
     */
    public static function create($class, $name)
    {
        $actor = Pool::getInstance()->get($class);
        $actor->initialization($name);
        return $actor;
    }

    /**
     * 是否存在
     * @param $name
     * @return bool
     */
    public static function has($name)
    {
        $result = ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->searchActor($name);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 销毁Actor
     * @param $name
     */
    public static function destroyActor($name)
    {
        self::call($name, 'destroy');
    }

    /**
     * 销毁全部
     */
    public static function destroyAllActor()
    {
        self::call(Actor::ALL_COMMAND, 'destroy');
    }

    /**
     * 恢复Actor
     * @param $worker_id
     * @return \Generator
     */
    public static function recovery($worker_id)
    {
        $data = CatCacheRpcProxy::getRpc()->offsetGet(Actor::SAVE_NAME);
        $delimiter = get_instance()->config->get("catCache.delimiter", ".");
        if ($data != null) {
            foreach ($data as $key => $value) {
                if ($value[ActorContext::WORKER_ID_KEY] == $worker_id) {
                    $path = Actor::SAVE_NAME . $delimiter . $key;
                    $saveContext = Pool::getInstance()->get(ActorContext::class)->initialization($path, $value[ActorContext::CLASS_KEY], $worker_id, $value);
                    $actor = Pool::getInstance()->get($saveContext->getClass());
                    $actor->initialization($key, $saveContext);
                }
            }
            if ($worker_id == 0) {
                $count = count($data);
                secho("Actor", "自动恢复了$count 个Actor。");
            }
        }
    }
}
