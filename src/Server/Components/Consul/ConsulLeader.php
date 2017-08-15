<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-14
 * Time: 下午4:00
 */

namespace Server\Components\Consul;


use Server\Components\Event\EventDispatcher;
use Server\Components\Process\ProcessManager;
use Server\Components\SDHelp\SDHelpProcess;
use Server\Coroutine\Coroutine;

class ConsulLeader
{
    protected $consul;
    protected $leader_name;
    protected $config;
    protected $sessionID;

    public function __construct()
    {
        $this->config = get_instance()->config;
        $this->leader_name = $this->config['consul']['leader_service_name'];
        $this->consul = get_instance()->getAsynPool('consul');
        swoole_timer_after(1000, function () {
            Coroutine::startCoroutine(function () {
                yield $this->leader();
                $this->serviceHealthCheck();
            });
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
            Coroutine::startCoroutine([$this, 'help_serviceHealthCheck'], [$watch, 0]);
        }
    }

    /**
     * @param $watch
     * @param $index
     */
    public function help_serviceHealthCheck($watch, $index)
    {
        $result = yield $this->consul->httpClient->setQuery(['passing' => true, 'index' => $index])->coroutineExecute('/v1/health/service/' . $watch)->setTimeout(11 * 60 * 1000)->noException(null);
        if ($result == null) {
            Coroutine::startCoroutine([$this, 'checkLeader'], [$watch, $index]);
            return;
        }
        $data[$watch] = $result['body'];
        //存儲在SDHelpProcess中
        ProcessManager::getInstance()->getProcess(SDHelpProcess::class)
            ->data[ConsulHelp::DISPATCH_KEY][$watch] = $result['body'];
        //分发到进程中去
        EventDispatcher::getInstance()->dispatch(ConsulHelp::DISPATCH_KEY, $data);
        //继续监听
        $index = $result['headers']['x-consul-index'];
        Coroutine::startCoroutine([$this, 'help_serviceHealthCheck'], [$watch, $index]);
    }

    /**
     * 过去SessionID
     * @return mixed
     */
    public function getSession()
    {
        if (empty($this->sessionID)) {
            $result = yield $this->consul->httpClient->setData(json_encode(['LockDelay' => 0, 'Behavior' => 'release', 'Name' => $this->leader_name]))->setMethod('PUT')->coroutineExecute('/v1/session/create')->noException(null);
            $this->sessionID = json_decode($result['body'], true)["ID"];
        }
        return $this->sessionID;
    }

    /**
     * 选举leader
     * @param int $index
     * @return \Generator
     */
    public function leader($index = 0)
    {
        $id = yield $this->getSession();
        $data = ['ip' => $this->config['consul']['bind_addr']];
        $result = yield $this->consul->httpClient->setQuery(['acquire' => $id])
            ->setData(json_encode($data))->setMethod('PUT')->coroutineExecute("/v1/kv/servers/$this->leader_name/leader");
        $leader = $result['body'];
        if ($leader == 'true') {//是leader
            $leader = true;
        } else {
            $leader = false;
        }
        //发送到进程
        EventDispatcher::getInstance()->dispatch(ConsulHelp::LEADER_KEY, $leader);
        //存儲在SDHelpProcess中
        ProcessManager::getInstance()->getProcess(SDHelpProcess::class)
            ->setData(ConsulHelp::LEADER_KEY, $leader);
        //继续监听
        Coroutine::startCoroutine([$this, 'checkLeader'], [$index]);
    }

    /**
     * 调用
     * @param int $index
     * @return \Generator|void
     */
    public function checkLeader($index = 0)
    {
        $result = yield $this->consul->httpClient->setMethod('GET')
            ->setQuery(['index' => $index])
            ->coroutineExecute("/v1/kv/servers/$this->leader_name/leader")->setTimeout(11 * 60 * 1000)->noException(null);
        if ($result == null) {
            Coroutine::startCoroutine([$this, 'checkLeader'], [$index]);
            return;
        }
        $body = json_decode($result['body'], true)[0];
        $index = $result['headers']['x-consul-index'];
        if (!isset($body['Session']))//代表没有Leader
        {
            Coroutine::startCoroutine([$this, 'leader'], [$index]);
        } else {
            Coroutine::startCoroutine([$this, 'checkLeader'], [$index]);
        }
    }
}