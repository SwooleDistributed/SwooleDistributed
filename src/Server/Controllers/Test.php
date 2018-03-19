<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-3-19
 * Time: 上午10:07
 */

namespace Server\Controllers;


use Server\CoreBase\Controller;

class Test extends Controller
{
    public function http_error()
    {
        throw new \Exception("test");
    }
}