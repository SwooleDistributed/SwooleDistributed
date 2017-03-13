<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午5:07
 */

namespace Server\Coroutine;


use Server\Memory\Pool;

class Coroutine
{
    /**
     * 可以根据需要更改定时器间隔，单位ms
     **/
    const TICK_INTERVAL = 1;
    private static $instance;
    private $routineList;
    private $tickId = -1;
    private $tickTime = 0;

    public function __construct()
    {
        self::$instance = $this;
        $this->routineList = [];
        $this->startTick();
    }

    private function startTick()
    {
        swoole_timer_tick(self::TICK_INTERVAL, function ($timerId) {
            $this->tickTime++;
            $this->tickId = $timerId;
            $this->run();
            get_instance()->tickTime = $this->getTickTime();
        });
    }


    private function run()
    {
        if (empty($this->routineList)) {
            return;
        }

        foreach ($this->routineList as $k => $task) {
            $task->run();
            if ($task->isFinished()) {
                $task->destroy();
                unset($this->routineList[$k]);
            }
        }
    }

    /**
     * 服务器运行到现在的毫秒数
     * @return int
     */
    public function getTickTime()
    {
        return $this->tickTime * self::TICK_INTERVAL;
    }

    /**
     * 初始化协程
     */
    public static function init()
    {
        if (self::$instance == null) {
            new Coroutine();
        }
    }

    /**
     * 获取实例
     * @return Coroutine
     */
    public static function getInstance()
    {
        return self::$instance;
    }

    /**
     * 启动一个协程
     * @param callable $function
     * @param array|null $params
     * @param GeneratorContext|null $generatorContext
     * @return mixed|null
     */
    public static function startCoroutine(callable $function, array $params = null, GeneratorContext $generatorContext = null)
    {
        if ($params == null) $params = [];
        $generator = call_user_func_array($function, $params);
        if ($generator instanceof \Generator) {
            if ($generatorContext == null) {
                $generatorContext = Pool::getInstance()->get(GeneratorContext::class);
                if (is_array($function)) {//代表不是匿名函数
                    $generatorContext->setController($function[0], get_class($function[0]), $function[1]);
                }
            }
            if (self::$instance != null) {//协程标志
                self::$instance->start($generator, $generatorContext);
            } else {//降级普通
                $corotineTask = Pool::getInstance()->get(CoroutineTask::class)->init($generator, $generatorContext);
                while (1) {
                    if ($corotineTask->isFinished()) {
                        $result = $generator->getReturn();
                        $corotineTask->destroy();
                        break;
                    }
                    $corotineTask->run();
                }
                return $result;
            }
        } else {
            return $generator;
        }
    }

    public function start(\Generator $routine, GeneratorContext $generatorContext)
    {
        $task = Pool::getInstance()->get(CoroutineTask::class)->init($routine, $generatorContext);
        $this->routineList[] = $task;
    }
}