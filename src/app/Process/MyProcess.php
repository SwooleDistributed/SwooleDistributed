<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-15
 * Time: 上午10:52
 */

namespace app\Process;

use Server\Components\Process\Process;

class MyProcess extends Process
{
    public function start($process)
    {

    }

    public function getData()
    {
        return '123';
    }
}