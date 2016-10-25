<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-18
 * Time: 下午3:28
 */

namespace Server\CoreBase;


abstract class CoroutineBase implements ICoroutineBase
{
    const MAX_TIMERS = 1000;
    /**
     * 请求语句
     * @var string
     */
    public $request;
    public $result;
    /**
     * 获取的次数，用于判断超时
     * @var int
     */
    public $getCount;

    public function __construct()
    {
        $this->result = CoroutineNull::getInstance();
    }

    public abstract function send($callback);

    public function getResult()
    {
        $this->getCount++;
        if ($this->getCount > self::MAX_TIMERS) {
            throw new SwooleException("[CoroutineTask]: Time Out!, [Request]: $this->request");
        }
        return $this->result;
    }
}