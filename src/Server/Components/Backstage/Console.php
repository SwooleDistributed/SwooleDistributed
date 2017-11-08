<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-10-30
 * Time: 下午7:25
 */

namespace Server\Components\Backstage;

use Server\Components\Cluster\ClusterProcess;
use Server\Components\Process\ProcessManager;
use Server\CoreBase\Controller;
use Server\Start;
use Server\SwooleMarco;

class Console extends Controller
{
    /**
     * onConnect
     * @return \Generator
     */
    public function back_onConnect()
    {
        $this->bindUid("#bs:" . getNodeName() . $this->fd);
        get_instance()->protect($this->fd);
        $this->addSub('$SYS/#');
        $this->destroy();
    }

    /**
     * onClose
     */
    public function back_onClose()
    {
        $this->destroy();
    }

    /**
     * 设置debug
     * @param $node_name
     * @param $bool
     */
    public function back_setDebug($node_name, $bool)
    {
        if (get_instance()->isCluster()) {
            ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_setDebug($node_name, $bool);
        } else {
            Start::setDebug($bool);
        }
        $this->autoSend("ok");
    }

    /**
     * reload
     * @param $node_name
     */
    public function back_reload($node_name)
    {
        if (get_instance()->isCluster()) {
            ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_reload($node_name);
        } else {
            get_instance()->server->reload();
        }
        $this->autoSend("ok");
    }

    /**
     * 获取所有的Sub
     */
    public function back_getAllSub()
    {
        $result = yield ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->my_getAllSub();
        $this->autoSend($result);
    }

    /**获取uid信息
     * @param $uid
     */
    public function back_getUidInfo($uid)
    {
        $uidInfo = yield get_instance()->getUidInfo($uid);
        $this->autoSend($uidInfo);
    }

    /**
     * @param $data
     */
    protected function autoSend($data)
    {
        switch ($this->request_type) {
            case SwooleMarco::TCP_REQUEST:
                $this->send($data);
                break;
            case SwooleMarco::HTTP_REQUEST:
                $this->http_output->setHeader("Access-Control-Allow-Origin", "*");
                $this->http_output->end($data);
                break;
        }
    }
}