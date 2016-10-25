<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午5:07
 */

namespace Server\CoreBase;


class Coroutine
{
//可以根据需要更改定时器间隔，单位ms
    const TICK_INTERVAL = 1;

    private $routineList;

    private $tickId = -1;

    public function __construct()
    {
        $this->routineList = [];
    }

    public function start(\Generator $routine, GeneratorContext $generatorContext)
    {
        $task = new CoroutineTask($routine, $generatorContext);
        $this->routineList[] = $task;
        $this->startTick();
    }

    private function startTick()
    {
        swoole_timer_tick(self::TICK_INTERVAL, function ($timerId) {
            $this->tickId = $timerId;
            $this->run();
        });
    }

    private function run()
    {
        if (empty($this->routineList)) {
            $this->stopTick();
            return;
        }

        foreach ($this->routineList as $k => $task) {
            $task->run();

            if ($task->isFinished()) {
                $task->destory();
                unset($this->routineList[$k]);
            }
        }
    }

    private function stopTick()
    {
        if ($this->tickId >= 0) {
            swoole_timer_clear($this->tickId);
        }
    }

    public function stop(\Generator $routine)
    {
        foreach ($this->routineList as $k => $task) {
            if ($task->getRoutine() == $routine) {
                unset($this->routineList[$k]);
            }
        }
    }
}