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

    public function init($eventType, $set)
    {
        $this->eventType = $eventType;
        $this->request = '[Event]' . $eventType;
        $this->set($set);
        EventDispatcher::getInstance()->add($this->eventType, [$this, 'send']);
        return $this->returnInit();
    }

    protected function coPush($data)
    {
        $this->result = $data;
        if ($this->chan == null) return;
        if (!$this->delayRecv || $this->startRecv) {
            $this->chan->push($data);
        }
    }

    public function send($event)
    {
        EventDispatcher::getInstance()->remove($this->eventType, [$this, 'send']);
        $this->coPush($event->data);
    }

    public function destroy()
    {
        parent::destroy();
        $this->eventType = null;
        Pool::getInstance()->push($this);
    }
}