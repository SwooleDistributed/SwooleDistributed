<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-1-2
 * Time: 下午1:47
 */

namespace Server\CoreBase;


use Server\Components\CatCache\CatCacheRpcProxy;
use Server\Components\Event\Event;
use Server\Components\Event\EventDispatcher;
use Server\Coroutine\Coroutine;
use Server\Memory\Pool;

abstract class Actor extends CoreBase
{
    /**
     * 存储所有的Actor
     * @var array
     */
    protected static $actors = [];
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
     * @param $name
     * @param $saveContext 用于恢复状态
     * @throws \Exception
     */
    public function initialization($name, $saveContext = null)
    {
        //这里仅仅对本进程做了判断，其实应该对所有进程做判断
        if (array_key_exists($name, self::$actors)) {
            throw new \Exception("Actor不允许重名");
        }
        $this->name = $name;
        self::$actors[$name] = $name;
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
        EventDispatcher::getInstance()->add($this->messageId, [$this, '_handle']);
        //接收管理器统一派发的消息
        EventDispatcher::getInstance()->add(self::SAVE_NAME . Actor::ALL_COMMAND, [$this, '_handle']);
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
            Coroutine::startCoroutine([$this, "registStatusHandle"], [$key, $value]);
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
            $this->saveContext["@status"][$key] = $value;
            //二维数组需要自己手动调用save
            $this->saveContext->save();
        }
        Coroutine::startCoroutine([$this, "registStatusHandle"], [$key, $value]);
    }

    /**
     * @param Event $event
     */
    public function _handle(Event $event)
    {
        Coroutine::startCoroutine([$this, $event->data['call']], $event->data['params']);
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

    public function destroy()
    {
        unset(self::$actors[$this->name]);
        EventDispatcher::getInstance()->removeAll($this->messageId);
        EventDispatcher::getInstance()->remove(self::SAVE_NAME . Actor::ALL_COMMAND, [$this, '_handle']);
        foreach ($this->timerIdArr as $id) {
            \swoole_timer_clear($id);
        }
        $this->saveContext->destroy();
        Pool::getInstance()->push($this->saveContext);
        $this->saveContext = null;
        $this->timerIdArr = [];
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
        $id = \swoole_timer_tick($ms, $callback, $user_param);
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
        $id = \swoole_timer_after($ms, $callback, $user_param);
        $this->timerIdArr[$id] = $id;
        return $id;
    }

    /**
     * 清除
     * @param $id
     */
    public function clearTimer($id)
    {
        \swoole_timer_clear($id);
        unset($this->timerIdArr[$id]);
    }

    /**
     * 呼叫Actor
     * @param $actorName
     * @param $call
     * @param $params
     * @internal param $data
     */
    public static function call($actorName, $call, $params = null)
    {
        $data['call'] = $call;
        $data['params'] = $params;
        EventDispatcher::getInstance()->dispatch(self::SAVE_NAME . $actorName, $data);
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
     * 获取本进程的Actors
     * @return array
     */
    public static function getActors()
    {
        return self::$actors;
    }


    /**
     * 恢复Actor
     * @param $worker_id
     */
    public static function recovery($worker_id)
    {
        $data = yield CatCacheRpcProxy::getRpc()[Actor::SAVE_NAME];
        $delimiter = get_instance()->config->get("catCache.delimiter", ".");
        if ($data != null) {
            if ($worker_id == 0) {
                $count = count($data);
                secho("Actor", "自动恢复了$count 个Actor。");
            }
            foreach ($data as $key => $value) {
                if ($value[ActorContext::WORKER_ID_KEY] == $worker_id) {
                    $path = Actor::SAVE_NAME . $delimiter . $key;
                    $saveContext = Pool::getInstance()->get(ActorContext::class)->initialization($path, $value[ActorContext::CLASS_KEY], $worker_id, $value);
                    $actor = Pool::getInstance()->get($saveContext->getClass());
                    $actor->initialization($key, $saveContext);
                }
            }
        }
    }
}