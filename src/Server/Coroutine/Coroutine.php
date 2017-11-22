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
        $this->run();
    }

    private function startTick()
    {
        swoole_timer_tick(self::TICK_INTERVAL, function ($timerId) {
            $this->tickTime++;
            $this->tickId = $timerId;
            get_instance()->tickTime = $this->getTickTime();
        });
    }

    /**
     * run
     */
    public function run()
    {
        foreach ($this->routineList as $k => $task) {
            if ($task->isFinished()) {
                $task->destroy();
                unset($this->routineList[$k]);
            } else {
                $task->run();
            }

        }
        swoole_timer_after(self::TICK_INTERVAL, [$this, 'run']);
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
     * @return mixed|null
     */
    public static function startCoroutine(callable $function, array $params = null)
    {
        if ($params == null) $params = [];
        try {
            $generator = call_user_func_array($function, $params);
        } catch (\Exception $e) {
            $function_name = '';
            if (is_array($function)) {
                $function_name = get_class($function[0]) . "::" . $function[1];
            }
            secho("EX", "---------------------[协程同步模式异常警告]---------------------" . date("Y-m-d h:i:s"));
            $message = "[" . $function_name . "]" . $e->getMessage();
            secho("EX", $message);
            get_instance()->log->addWarning($message);
            $result = new CoroutineTaskException($e->getMessage(), $e->getCode());
            return $result;
        }
        if ($generator instanceof \Generator) {
            if (self::$instance != null) {//协程标志
                self::$instance->start($generator);
            } else {//降级普通
                $corotineTask = Pool::getInstance()->get(CoroutineTask::class)->init($generator);
                while (1) {
                    if ($corotineTask->isFinished()) {
                        try {
                            $result = $generator->getReturn();
                        } catch (\Exception $e) {
                            $e = $corotineTask->getEx();
                            $function_name = '';
                            if (is_array($function)) {
                                $function_name = get_class($function[0]) . "::" . $function[1];
                            }
                            secho("EX", "---------------------[协程同步模式异常警告]---------------------" . date("Y-m-d h:i:s"));
                            $message = "[" . $function_name . "]" . $e->getMessage();
                            secho("EX", $message);
                            get_instance()->log->addWarning($message);
                            $result = new CoroutineTaskException($e->getMessage(), $e->getCode());
                        }
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

    /**
     * @param \Generator $routine
     */
    public function start(\Generator $routine)
    {
        $task = Pool::getInstance()->get(CoroutineTask::class)->init($routine);
        $this->routineList[] = $task;
        $task->run();
    }

    /**
     * getStatus
     */
    public function getStatus()
    {
        return count($this->routineList);
    }
}