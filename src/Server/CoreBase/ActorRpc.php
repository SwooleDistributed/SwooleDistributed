<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-1-19
 * Time: 上午10:14
 */

namespace Server\CoreBase;


use Server\Components\Event\EventCoroutine;
use Server\Memory\Pool;

class ActorRpc
{
    public $actorName;
    public $beginId = null;

    /**
     * @param $actorName
     * @return $this
     */
    public function init($actorName)
    {
        $this->actorName = $actorName;
        $this->beginId = null;
        return $this;
    }

    /**
     * 开启事务
     * @param callable|null $run
     * @param callable|null $fail
     * @param int $timeOut
     * @return null
     * @throws \Exception
     */
    public function beginCo(callable $run = null, callable $fail = null, $timeOut = 10000)
    {
        $this->beginId = Actor::call($this->actorName, "begin", null, false, null, function (EventCoroutine $eventCoroutine) use ($timeOut) {
            $eventCoroutine->setTimeout($timeOut);
        });
        if ($run != null) {
            try {
                $run();
            } catch (\Exception $e) {
                if ($fail != null) $fail();
            } finally {
                $this->end();
            }
        }
        return $this->beginId;
    }

    /**
     * 结束事务
     * @throws \Exception
     */
    public function end()
    {
        if (empty($this->beginId)) {
            return;
        }
        Actor::call($this->actorName, "end", [$this->beginId], true, $this->beginId);
        $this->beginId = null;
    }

    /**
     * @param $name
     * @param $arguments
     * @return null
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        return Actor::call($this->actorName, $name, $arguments, false, $this->beginId);
    }

    /**
     * 没有返回的call
     * @param $name
     * @param $arguments
     * @throws \Exception
     */
    public function oneWayCall($name, $arguments = null)
    {
        Actor::call($this->actorName, $name, $arguments, true, $this->beginId);
    }

    /**
     * 回收
     */
    public function destroy()
    {
        Pool::getInstance()->push($this);
    }
}