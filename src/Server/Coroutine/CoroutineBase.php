<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-18
 * Time: 下午3:28
 */

namespace Server\Coroutine;


use Server\CoreBase\SwooleException;

abstract class CoroutineBase implements ICoroutineBase
{
    /**
     * 请求语句
     * @var string
     */
    public $request;
    /**
     * 结果
     * @var CoroutineNull
     */
    public $result;
    /**
     * 获取的次数，用于判断超时
     * @var int
     */
    public $getCount;
    protected $MAX_TIMERS = 0;
    /**
     * 是否启用断路器
     * @var bool
     */
    protected $useFuse;
    /**
     * 降级操作回复result
     * @var callable
     */
    protected $downgrade;
    /**
     * @var CoroutineTask
     */
    private $coroutineTask;
    /**
     * @var bool
     */
    private $isFaile;

    public function __construct()
    {
        $this->MAX_TIMERS = get_instance()->config->get('coroution.timerOut', 1000);
        $this->result = CoroutineNull::getInstance();
        $this->getCount = 0;
    }

    public abstract function send($callback);

    public function getResult()
    {
        if ($this->isFaile) {
            if (empty($this->downgrade)) {
                //没有降级操作就直接快速失败
                $this->fastFail();
            } else {
                $this->result = call_user_func($this->downgrade);
                return $this->result;
            }
        }
        $this->getCount++;
        if ($this->getCount > $this->MAX_TIMERS && $this->result == CoroutineNull::getInstance()) {
            $this->isFaile = true;
            $this->onTimerOutHandle();
            throw new SwooleException("[CoroutineTask]: Time Out!, [Request]: $this->request");
        }
        return $this->result;
    }

    /**
     * 快速失败
     */
    protected function fastFail()
    {
        throw new \Exception('Circuit breaker');
    }

    /**
     * 超时的处理
     */
    protected function onTimerOutHandle()
    {

    }

    /**
     * 设置协程任务
     * @param $coroutineTask
     */
    public function setCoroutineTask($coroutineTask)
    {
        $this->coroutineTask = $coroutineTask;
    }

    /**
     * 立即执行任务的下一个步骤
     */
    public function immediateExecution()
    {
        if (isset($this->coroutineTask)) {
            $this->coroutineTask->run();
        }
    }

    /**
     * destory
     */
    public function destory()
    {
        if ($this->useFuse) {
            if ($this->isFaile) {
                Fuse::getInstance()->commitTimeOutOrFaile($this->request);
            } else {
                Fuse::getInstance()->commitSuccess($this->request);
            }
        }
        $this->result = CoroutineNull::getInstance();
        $this->MAX_TIMERS = get_instance()->config->get('coroution.timerOut', 1000);
        $this->getCount = 0;
        unset($this->coroutineTask);
        unset($this->request);
        $this->downgrade = null;
        $this->isFaile = false;
    }

    /**
     * 设置超时时间
     * @param $maxtime
     * @return $this
     */
    public function setTimeout($maxtime)
    {
        $this->MAX_TIMERS = $maxtime;
        return $this;
    }

    /**
     * 设置降级操作，如果没有设置断路器工作时将会进行快速失败
     * @param callable $func
     * @return $this
     * @throws SwooleException
     */
    public function setDowngrade(callable $func)
    {
        if (!$this->useFuse) {
            throw new SwooleException('not supper fuse');
        }
        $this->downgrade = $func;
        return $this;
    }

    /**
     * 断路器依赖$request
     * 使用断路器
     */
    protected function fuse()
    {
        $this->useFuse = true;
        $state = Fuse::getInstance()->requestRun($this->request);
        if ($state == Fuse::CLOSE) {
            $this->isFaile = true;
            return false;
        }
        return true;
    }
}