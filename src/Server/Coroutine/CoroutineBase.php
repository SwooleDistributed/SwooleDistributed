<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-18
 * Time: 下午3:28
 */

namespace Server\Coroutine;


use Server\CoreBase\RPCThrowable;
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

    public function getDelayRecv()
    {
        return $this->delayRecv;
    }

    protected function coPush($data)
    {
        $this->result = $data;
        if ($this->chan == null) return;
        if (!$this->delayRecv || $this->startRecv) {
            go(function () use ($data) {
                $this->chan->push($data);
            });
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

    public function recv(callable $fuc = null)
    {
        if ($this->isFaile && $this->useFuse) {//启动了断路器并且还失败了
            if (empty($this->downgrade)) {
                //没有降级操作就直接快速失败
                $this->result = $this->fastFail();
            } else {
                $this->result = sd_call_user_func($this->downgrade);
            }
        }
        $this->startRecv = true;
        if ($this->result !== CoroutineNull::getInstance()) {//有值了
            $result = $this->getResult($this->result);
            if ($fuc != null) {
                $fuc($result);
            }
            $this->destroy();
            return $result;
        } else {
            $this->chan = new \chan();
        }
        $result = $this->chan->pop($this->MAX_TIMERS/1000);
        //没有错误码就是正常的
        if($this->chan->errCode==0){
            $result = $this->getResult($result);
        }else{//超时
            //有降级函数则访问降级函数
            if (empty($this->downgrade)) {
                $result = new SwooleException("[CoroutineTask]: Time Out!, [Request]: $this->request");
            } else {
                $result = sd_call_user_func($this->downgrade);
                $this->isFaile = true;
            }
            $this->onTimerOutHandle();
            $result = $this->getResult($result);
        }
        if ($fuc != null) {
            $fuc($result);
        }
        $this->destroy();
        return $result;
    }

    public function getResult($result)
    {
        if ($result instanceof RPCThrowable) {
            $result = $result->build();
        }
        if ($result instanceof \Throwable) {
            //迁移操作
            if ($result instanceof CoroutineChangeToken) {
                $this->token = $result->token;
                $result = $this->getResult($this->chan->pop());
            } else {
                $this->isFaile = true;
                if (!$this->noException) {
                    $this->destroy();
                    throw $result;
                } else {
                    $result = $this->noExceptionReturn;
                }
            }
        }
        return $result;
    }

    public abstract function send($callback);

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
        return new \Exception('Circuit breaker');
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
     * @return int|mixed|null
     */
    public function getTimeout()
    {
        return $this->MAX_TIMERS;
    }

    /**
     * 设置降级操作，如果没有设置断路器工作时将会进行快速失败
     * @param callable $func
     * @return $this
     */
    public function setDowngrade(callable $func)
    {
        $this->downgrade = $func;
        return $this;
    }

    /**
     * 断路器
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