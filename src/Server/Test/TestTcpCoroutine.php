<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-12-30
 * Time: 下午4:37
 */

namespace Server\Test;


use Server\Coroutine\CoroutineBase;
use Server\Coroutine\CoroutineNull;

class TestTcpCoroutine extends CoroutineBase
{
    /**
     * @var \Server\CoreBase\Controller|void
     */
    private $controller;

    public function __construct($port, $data, $uid = 0)
    {
        parent::__construct();
        $this->request = '#TcpRequest:' . json_encode($data);
        $pack = get_instance()->portManager->getPack($port);
        try {
            $this->controller = get_instance()->onSwooleReceive(get_instance()->server, $uid, 0, $pack->pack($data), $port);
        } catch (\Exception $e) {
            $this->controller = CoroutineNull::getInstance();
        }
        if ($this->controller == null) {
            $this->controller = CoroutineNull::getInstance();
        }
    }

    public function send($callback)
    {

    }

    public function getResult()
    {
        $result = parent::getResult();
        if ($this->controller == CoroutineNull::getInstance()) {
            return null;
        }
        if ($this->controller->is_destroy) {
            $result = $this->controller->getTestUnitResult();
        }
        return $result;
    }
}