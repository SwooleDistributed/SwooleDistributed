<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\CoreBase;

use Server\Coroutine\CoroutineBase;

/**
 * 用于并发选择1个结果，相当于go的select
 * Class SelectCoroutine
 * @package Server\CoreBase
 */
class SleepCoroutine extends CoroutineBase
{

    public function getResult()
    {
        $this->getCount++;
        if ($this->getCount > $this->MAX_TIMERS) {
            return true;
        }
        return $this->result;
    }

    public function send($callback)
    {

    }
}