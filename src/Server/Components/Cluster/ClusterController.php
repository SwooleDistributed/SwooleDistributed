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

/**
 * 集群控制器
 * Class Cluster
 * @package Server\Controllers
 */
class ClusterController
{
    /**
     * 同步数据
     * @param $node_name
     * @param $uids
     */
    public function syncNodeData($node_name, $uids)
    {
        $uids = array_values($uids);
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_syncData($node_name, $uids);
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

    public function kickUid($uid)
    {
        get_instance()->kickUid($uid, true);
    }

    public function pub($sub, $data)
    {
        ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->th_pub($sub, $data);
    }

    public function dispatchEvent($type, $data)
    {
        EventDispatcher::getInstance()->dispatch($type, $data, false, true);
    }
}