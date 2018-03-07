<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-18
 * Time: 上午10:52
 */

namespace Server\Components\Cluster;

use Server\Components\Event\EventDispatcher;
use Server\Components\Process\ProcessManager;
use Server\Components\SDHelp\SDHelpProcess;
use Server\CoreBase\Actor;
use Server\CoreBase\Child;
use Server\Start;

/**
 * 集群控制器
 * Class Cluster
 * @package Server\Controllers
 */
class ClusterController extends Child
{
    /**
     * 同步数据
     * @param $node_name
     * @param $datas
     * @param $type
     */
    public function syncNodeData($node_name, $datas, $type)
    {
        $datas = array_values($datas);
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_syncData($node_name, $datas, $type);
    }

    /**
     * 添加数据
     * @param $node_name
     * @param $uid
     */
    public function addNodeUid($node_name, $uid)
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_addUid($node_name, $uid);
    }

    /**
     * 移除数据
     * @param $node_name
     * @param $uid
     */
    public function removeNodeUid($node_name, $uid)
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_removeUid($node_name, $uid);
    }

    public function sendToUid($uid, $data)
    {
        get_instance()->sendToUid($uid, $data, true);
    }

    public function sendToUids($uids, $data)
    {
        get_instance()->sendToUids($uids, $data, true);
    }

    public function sendToAll($data)
    {
        get_instance()->sendToAll($data, true);
    }

    public function sendToAllFd($data)
    {
        get_instance()->sendToAllFd($data, true);
    }

    public function kickUid($uid)
    {
        get_instance()->kickUid($uid, true);
    }

    public function pub($sub, $data, $excludeUids)
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_pub($sub, $data, $excludeUids);
    }

    public function dispatchEvent($type, $data)
    {
        EventDispatcher::getInstance()->dispatch($type, $data, false, true);
    }

    public function setDebug($bool)
    {
        Start::setDebug($bool);
    }

    public function reload()
    {
        get_instance()->server->reload();
    }

    public function status()
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_status();
    }

    /**
     * @param $uid
     * @return mixed|null
     */
    public function getUidInfo($uid)
    {
        $fd = get_instance()->getFdFromUid($uid);
        if (!empty($fd)) {
            $fdInfo = get_instance()->getFdInfo($fd);
            $fdInfo['node'] = getNodeName();
            return $fdInfo;
        } else {
            return [];
        }
    }

    /**
     * @return mixed
     */
    public function getAllSub()
    {
        $result = ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->th_getAllSub();
        return $result;
    }

    /**
     * 获取统计信息
     * @param $index
     * @param $num
     * @return mixed
     */
    public function getStatistics($index, $num)
    {
        $result = ProcessManager::getInstance()->getRpcCall(SDHelpProcess::class)->getStatistics($index, $num);
        return $result;
    }

    /**
     * @param $topic
     * @return mixed
     */
    public function getSubMembersCount($topic)
    {
        $result = ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->th_getSubMembersCount($topic);
        return $result;
    }

    /**
     * @param $topic
     * @return mixed
     */
    public function getSubMembers($topic)
    {
        $result = ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->th_getSubMembers($topic);
        return $result;
    }

    /**
     * @param $uid
     * @return mixed
     */
    public function getUidTopics($uid)
    {
        $result = ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->th_getUidTopics($uid);
        return $result;
    }

    /**
     * @param $node_name
     * @param $actor
     */
    public function addNodeActor($node_name, $actor)
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_addActor($node_name, $actor);
    }

    /**
     * @param $node_name
     * @param $actor
     */
    public function removeNodeActor($node_name, $actor)
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_removeActor($node_name, $actor);
    }

    /**
     * @param $actor
     * @param $data
     */
    public function callActor($actor, $data)
    {
        EventDispatcher::getInstance()->dispatch(Actor::SAVE_NAME . $actor, $data, false, true);
    }

    /**
     * @param $workerId
     * @param $token
     * @param $result
     */
    public function callActorBack($workerId, $token, $result)
    {
        EventDispatcher::getInstance()->dispathToWorkerId($workerId, $token, $result);
    }
}