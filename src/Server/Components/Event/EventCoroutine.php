<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\Components\Event;

use Server\Coroutine\CoroutineBase;
use Server\Memory\Pool;

class EventCoroutine extends CoroutineBase
{

    public $eventType;

    public function __construct()
    {
        parent::__construct();
    }

    public function init($eventType)
    {
        $this->eventType = $eventType;
        $this->request = '[Event]' . $eventType;
        EventDispatcher::getInstance()->add($this->eventType, [$this, 'send']);
        return $this;
    }

    public function send($event)
    {
        $this->result = $event->data;
        EventDispatcher::getInstance()->remove($this->eventType, [$this, 'send']);
    }

    public function destroy()
    {
        parent::destroy();
        $this->eventType = null;
        Pool::getInstance()->push($this);
    }
}