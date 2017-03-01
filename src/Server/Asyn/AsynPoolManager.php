<?php
/**
 * 异步连接池管理器
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-25
 * Time: 上午10:25
 */

namespace Server\Asyn;


class AsynPoolManager
{
    protected $registDir = [];

    public function __construct()
    {
    }

    /**
     * 注册
     * @param $asyn
     */
    public function registAsyn(IAsynPool $asyn)
    {
        $this->registDir[$asyn->getAsynName()] = $asyn;
    }
}