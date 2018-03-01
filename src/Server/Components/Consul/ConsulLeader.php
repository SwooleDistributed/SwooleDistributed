<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-14
 * Time: 下午4:00
 */

namespace Server\Components\Consul;


use Server\Asyn\HttpClient\HttpClient;
use Server\Components\Event\EventDispatcher;
use Server\Components\SDHelp\SDHelpProcess;
use Server\Start;

class ConsulLeader
{
    protected $consul_service_client;
    protected $consul_leader;
    protected $leader_name;
    protected $config;
    protected $sessionID;
    /**
     * @var SDHelpProcess
     */
    protected $sdHelpProcess;

    public function __construct($sdHelpProcess)
    {
        $this->sdHelpProcess = $sdHelpProcess;
        $this->config = get_instance()->config;
        $this->leader_name = $this->config['consul']['leader_service_name'];
        $this->consul_leader = new HttpClient(null, 'http://127.0.0.1:8500');
        swoole_timer_after(2000, function () {
            $this->leader();
            $this->serviceHealthCheck();
        });
    }

    /**
     * 服务器启动后正常情况是会超时的，因为consul服务器还未完全启动好，这地方只是为了reload的时候不会丢失而做的请求。。
     * 开始健康检查
     */
    public function serviceHealthCheck()
    {
        $watches = $this->config->get('consul.watches', []);
        foreach ($watches as $watch) {
            $this->consul_service_client[$watch] = new HttpClient(null, 'http://127.0.0.1:8500');
            $this->help_serviceHealthCheck($watch, 0);
        }
    }

    /**
     * @param $watch
     * @param $index
     */
    public function help_serviceHealthCheck($watch, $index)
    {
        $this->consul_service_client[$watch]->setQuery(['passing' => true, 'index' => $index])->execute('/v1/health/service/' . $watch, function ($data) use ($watch, $index) {
            if ($data['statusCode'] < 0) {
                $this->help_serviceHealthCheck($watch, $index);
                return;
            }
            $result[$watch] = $data['body'];
            //存儲在SDHelpProcess中
            $this->sdHelpProcess->data[ConsulHelp::DISPATCH_KEY][$watch] = $data['body'];
            //分发到进程中去
            EventDispatcher::getInstance()->dispatch(ConsulHelp::DISPATCH_KEY, $result);
            //继续监听
            $index = $data['headers']['x-consul-index'];
            $this->help_serviceHealthCheck($watch, $index);
        });
    }

    /**
     * 过去SessionID
     * @param $call
     */
    public function getSession($call)
    {
        if (empty($this->sessionID)) {
            $this->consul_leader->setData(json_encode(['LockDelay' => 0, 'Behavior' => 'release', 'Name' => $this->leader_name]))->setMethod('PUT')->execute('/v1/session/create', function ($data) use ($call) {
                $this->sessionID = json_decode($data['body'], true)["ID"];
                $call($this->sessionID);
            });
        } else {
            $call($this->sessionID);
        }
    }

    /**
     * 选举leader
     * @param int $index
     * @return \Generator
     */
    public function leader($index = 0)
    {
        $this->getSession(function ($id) use ($index) {
            $data = ['ip' => getBindIp()];
            $this->consul_leader->setQuery(['acquire' => $id])
                ->setData(json_encode($data))->setMethod('PUT')->execute("/v1/kv/servers/$this->leader_name/leader", function ($data) use ($index) {
                    $leader = $data['body'];
                    if ($leader == 'true') {//是leader
                        $leader = true;
                    } else {
                        $leader = false;
                    }
                    Start::setLeader($leader);
                    //继续监听
                    $this->checkLeader($index);
                });
        });
    }

    /**
     * 调用
     * @param int $index
     * @return \Generator|void
     */
    public function checkLeader($index = 0)
    {
        $this->consul_leader->setMethod('GET')
            ->setQuery(['index' => $index])
            ->execute("/v1/kv/servers/$this->leader_name/leader", function ($data) use ($index) {
                if ($data['statusCode'] < 0) {
                    $this->checkLeader($index);
                    return;
                }
                $body = json_decode($data['body'], true)[0];
                $index = $data['headers']['x-consul-index'];
                if (!isset($body['Session']))//代表没有Leader
                {
                    $this->leader($index);
                } else {
                    $this->checkLeader($index);
                }
            });
    }
}