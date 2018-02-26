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
     * @var bool
     */
    protected $isFaile;
    /**
     * @var bool
     */
    protected $noException;

    protected $token;

    protected $noExceptionReturn;

    protected $chan;

    protected $delayRecv;

    protected $startRecv;

    public function __construct()
    {
        $this->MAX_TIMERS = get_instance()->config->get('coroution.timerOut', 1000);
        $this->result = CoroutineNull::getInstance();
        $this->noException = false;
    }

    protected function set($call)
    {
        if ($call != null) {
            $call($this);
        }
    }

    //设置延时Recv，这是需要手动调用recv才能获取结果
    public function setDelayRecv()
    {
        $this->delayRecv = true;
    }

    protected function coPush($data)
    {
        if ($this->chan == null) return;
        $this->result = $data;
        if (!$this->delayRecv || $this->startRecv) {
            $this->chan->push($data);
        }
    }

    public function returnInit()
    {
        if ($this->delayRecv) {
            return $this;
        } else {
            return $this->recv();
        }
    }

    public function recv()
    {
        $this->startRecv = true;
        if ($this->result !== CoroutineNull::getInstance()) {//有值了
            $result = $this->getResult($this->result);
            return $result;
        } else {
            $this->chan = new \chan();
        }
        $readArr = [$this->chan];
        $writeArr = null;
        $type = \chan::select($readArr, $writeArr, $this->MAX_TIMERS / 1000);
        if ($type) {
            $result = $this->chan->pop();
            $result = $this->getResult($result);
        } else {//超时
            $result = new SwooleException("[CoroutineTask]: Time Out!, [Request]: $this->request");
            $this->onTimerOutHandle();
            $result = $this->getResult($result);
        }
        return $result;
    }

    protected function getResult($result)
    {
        if ($result instanceof \Throwable) {
            if (!$this->noException) {
                $this->destroy();
                throw $result;
            } else {
                $this->result = $this->noExceptionReturn;
            }
        }
        $this->destroy();
        return $result;
    }

    public abstract function send($callback);

    /* public function getResult()
     {
         if ($this->isFaile && $this->useFuse) {
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
             $this->getCount = getTickTime();
             $this->result = CoroutineNull::getInstance();
         }
         if ((getTickTime() - $this->getCount) > $this->MAX_TIMERS && $this->result instanceof CoroutineNull) {
             $this->onTimerOutHandle();
             if (!$this->noException) {
                 $this->isFaile = true;
                 $ex = new SwooleException("[CoroutineTask]: Time Out!, [Request]: $this->request");
                 $this->destroy();
                 throw $ex;
             } else {
                 $this->result = $this->noExceptionReturn;
             }
         }
         return $this->result;
     }*/

    /**
     * dump
     */
    public function dump()
    {
        secho("DUMP", $this->request);
        return $this;
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
     * destroy
     */
    public function destroy()
    {
        if ($this->chan != null) {
            $this->chan->close();
        }
        $this->chan = null;
        if ($this->useFuse) {
            if ($this->isFaile) {
                Fuse::getInstance()->commitTimeOutOrFaile($this->request);
            } else {
                Fuse::getInstance()->commitSuccess($this->request);
            }
        }
        $this->result = CoroutineNull::getInstance();
        $this->delayRecv = false;
        $this->startRecv = false;
        $this->MAX_TIMERS = get_instance()->config->get('coroution.timerOut', 1000);
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