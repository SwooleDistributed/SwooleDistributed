<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-1-2
 * Time: 下午1:47
 */

namespace Server\CoreBase;


use Server\Components\Event\Event;
use Server\Components\Event\EventDispatcher;
use Server\Coroutine\Coroutine;
use Server\Memory\Pool;

class Actor extends CoreBase
{
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
     * @param $name
     */
    public function initialization($name)
    {
        $this->messageId = 'Actor:' . $name;
        EventDispatcher::getInstance()->add($this->messageId, function (Event $event) {
            Coroutine::startCoroutine([$this, $event->data['call']], $event->data['params']);
        });
    }

    /**
     * 创建附属actor
     * @param $class
     * @return mixed
     */
    protected function createActor($class)
    {
        $actor = Pool::getInstance()->get($class);
        $this->addChild($actor);
        return $actor;
    }

    public function destroy()
    {
        EventDispatcher::getInstance()->removeAll($this->messageId);
        foreach ($this->timerIdArr as $id) {
            \swoole_timer_clear($id);
        }
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
        EventDispatcher::getInstance()->dispatch('Actor:' . $actorName, $data);
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
}