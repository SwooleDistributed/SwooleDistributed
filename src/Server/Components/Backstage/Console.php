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
use Server\Components\SDHelp\SDHelpProcess;
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
     * 获取所有的uid
     */
    public function back_getAllUids()
    {
        $uids = yield get_instance()->coroutineGetAllUids();
        $this->autoSend($uids);
    }

    /**
     * 获取sub的uid
     * @param $topic
     */
    public function back_getSubUid($topic)
    {
        $uids = yield get_instance()->getSubMembersCoroutine($topic);
        $this->autoSend($uids);
    }

    /**
     * 获取uid所有的订阅
     * @param $uid
     */
    public function back_getUidTopics($uid)
    {
        $topics = yield get_instance()->getUidTopicsCoroutine($uid);
        $this->autoSend($topics);
    }

    /**
     * 获取统计信息
     * @param $node_name
     * @param $index
     * @param $num
     */
    public function back_getStatistics($node_name, $index, $num)
    {
        if (!get_instance()->isCluster() || $node_name == getNodeName()) {
            $map = yield ProcessManager::getInstance()->getRpcCall(SDHelpProcess::class)->getStatistics($index, $num);
        } else {
            $map = yield ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->my_getStatistics($node_name, $index, $num);
        }
        $this->autoSend($map);
    }

    /**
     * @param $data
     */
    protected function autoSend($data)
    {
        if (is_array($data) || is_object($data)) {
            $output = json_encode($data, JSON_UNESCAPED_UNICODE);
        } else {
            $output = $data;
        }
        switch ($this->request_type) {
            case SwooleMarco::TCP_REQUEST:
                $this->send($output);
                break;
            case SwooleMarco::HTTP_REQUEST:
                $this->http_output->setHeader("Access-Control-Allow-Origin", "*");
                $this->http_output->end($output);
                break;
        }
    }
}