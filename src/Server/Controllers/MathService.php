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
    public function add($one,$two)
    {
        $this->send($one+$two);
    }

    /**
     * Http Add
     */
    public function http_add()
    {
        $one = $this->http_input->get('one');
        $two = $this->http_input->get('two');
        $this->http_output->end($one+$two);
    }

    public function sum($sum)
    {
        $sum_q = 0;
        for($i=0;$i<$sum;$i++){
            $sum_q+=$i;
        }
        $this->send($sum_q);
    }

    public function http_sum()
    {
        $sum = $this->http_input->get('sum');
        $sum_q = 0;
        for($i=0;$i<$sum;$i++){
            $sum_q+=$i;
        }
        $this->http_output->end($sum_q);
    }
}