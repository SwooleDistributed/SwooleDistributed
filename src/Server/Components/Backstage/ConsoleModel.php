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
use Server\CoreBase\ChildProxy;
use Server\CoreBase\Model;
use Server\Start;

class ConsoleModel extends Model
{
    protected $enable = false;
    protected $websocket_port = false;

    public function __construct(string $proxy = ChildProxy::class)
    {
        parent::__construct($proxy);
        $this->enable = $this->config->get("backstage.enable");
        $this->websocket_port = $this->config->get("backstage.websocket_port");
    }

    /**
     * 获取Node状态
     * @return void
     * @throws \Server\Asyn\MQTT\Exception
     * @throws \Exception
     */
    public function getNodeStatus()
    {
        $port = get_instance()->getPort($this->websocket_port);
        if (count($port->connections) == 0) return;
        if (Start::isLeader() && $this->enable) {
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