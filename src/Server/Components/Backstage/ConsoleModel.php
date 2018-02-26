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
use Server\CoreBase\Model;
use Server\Start;

class ConsoleModel extends Model
{
    /**
     * 获取Node状态
     * @return \Generator
     */
    public function getNodeStatus()
    {
        if (Start::isLeader()) {
            $status["isCluster"] = get_instance()->isCluster();
            if (get_instance()->isCluster()) {
                ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_status();
                $nodes = ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->getNodes();
                sort($nodes);
                $status["nodes"] = $nodes;
            } else {
                $status["nodes"] = [getNodeName()];
                get_instance()->getStatus();
            }
            get_instance()->pub('$SYS/status', $status);
        }
    }

}