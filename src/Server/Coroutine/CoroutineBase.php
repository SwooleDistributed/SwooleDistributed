<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-18
 * Time: 下午3:28
 */

namespace Server\Coroutine;


use Server\CoreBase\SwooleException;

/**
 * 这里抛出异常之前请销毁自身
 * Class CoroutineBase
 * @package Server\Coroutine
 */
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
    /**
     * @var bool
     */
    private $noException;

    protected $token;

    private $noExceptionReturn;

    public function __construct()
    {
        $this->MAX_TIMERS = get_instance()->config->get('coroution.timerOut', 1000);
        $this->result = CoroutineNull::getInstance();
        $this->getCount = 0;
        $this->noException = false;
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
        //迁移操作
        if ($this->result instanceof CoroutineChangeToken) {
            $this->token = $this->result->token;
            $this->getCount = 0;
            $this->result = CoroutineNull::getInstance();
        }
        $this->getCount++;
        if ($this->getCount > $this->MAX_TIMERS && $this->result == CoroutineNull::getInstance()) {
            $this->isFaile = true;
            $this->onTimerOutHandle();
            if (!$this->noException) {
                $ex = new SwooleException("[CoroutineTask]: Time Out!, [Request]: $this->request");
                $this->destroy();
                throw $ex;
            } else {
                $this->result = null;
            }
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
     * destroy
     */
    public function destroy()
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
        $this->coroutineTask = null;
        $this->request = null;
        $this->downgrade = null;
        $this->isFaile = false;
        $this->noException = false;
        $this->noExceptionReturn = null;
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

    /**
     * 不返回超时异常，直接返回$return的值
     * @param null $return
     * @return $this
     */
    public function noException($return = null)
    {
        $this->noException = true;
        $this->noExceptionReturn = $return;
        return $this;
    }
}