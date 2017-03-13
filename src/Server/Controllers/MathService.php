<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-13
 * Time: 下午2:12
 */

namespace Server\Controllers;


use Server\CoreBase\Controller;

/**
 * 测RPC的数学服务
 * Class MathService
 * @package app\Controllers
 */
class MathService extends Controller
{
    /**
     * RPC Add
     * @param $one
     * @param $two
     */
    public function add($one, $two)
    {
        $this->send($one + $two);
    }

    /**
     * Http Add
     */
    public function http_add()
    {
        $one = $this->http_input->get('one');
        $two = $this->http_input->get('two');
        $this->http_output->end($one + $two);
    }
}