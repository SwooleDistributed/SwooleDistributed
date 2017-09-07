<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-1
 * Time: 下午4:20
 */

namespace Server\Coroutine;


interface ICoroutineBase
{
    function send($callback);

    function getResult();

    function destroy();

    function setCoroutineTask($coroutineTask);

    function immediateExecution();
}