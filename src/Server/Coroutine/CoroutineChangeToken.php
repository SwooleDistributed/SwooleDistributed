<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-13
 * Time: 上午11:16
 */

namespace Server\Coroutine;


class CoroutineChangeToken
{
    public $token;
    public function __construct($token)
    {
        $this->token = $token;
    }
}