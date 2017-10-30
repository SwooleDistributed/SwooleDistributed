<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-10-26
 * Time: 下午5:46
 */

namespace Server\Coroutine;


class CoroutineTaskException
{
    protected $message;
    protected $code;

    public function __construct($message, $code = 0)
    {
        $this->message = $message;
        $this->code = $code;
    }

    public function getMessage()
    {
        return $this->message;
    }

    public function getCode()
    {
        return $this->code;
    }
}