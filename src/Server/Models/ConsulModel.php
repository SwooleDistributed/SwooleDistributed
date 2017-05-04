<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-4-28
 * Time: 下午3:09
 */

namespace Server\Models;


use Server\Asyn\HttpClient\HttpClientPool;
use Server\Components\Consul\ConsulHelp;
use Server\CoreBase\Model;
use Server\Coroutine\Coroutine;
use Server\SwooleMarco;

class ConsulModel extends Model
{
    /**
     * @var HttpClientPool
     */
    protected $consul;
    protected $leader_name;
    public function initialization(&$context)
    {
        parent::initialization($context);
        $this->consul = get_instance()->getAsynPool('consul');
        $this->leader_name = $this->config['consul']['leader_service_name'];
    }

    /**
     * 过去SessionID
     * @return mixed
     */
    public function getSession()
    {
        if(empty(ConsulHelp::getSessionID())) {
            do {
                $result = yield $this->consul->httpClient->setData(json_encode(['LockDelay'=>0,'Behavior'=>'release','Name' => $this->leader_name]))->setMethod('PUT')->coroutineExecute('/v1/session/create')->setTimeout(100)->noException(null);
            } while ($result == null);
            $id = json_decode($result['body'], true)["ID"];
            get_instance()->sendToAllWorks(SwooleMarco::CONSUL_SERVICES_SESSION, $id, ConsulHelp::class . "::setSession");
        }
        return ConsulHelp::getSessionID();
    }

    /**
     * 选举leader
     * @param int $index
     * @return \Generator
     */
    public function leader($index = 0)
    {
        $id = yield $this->getSession();
        $data = ['ip'=>$this->config['consul']['bind_addr']];
        $result = yield $this->consul->httpClient->setQuery(['acquire'=>$id])
            ->setData(json_encode($data))->setMethod('PUT')->coroutineExecute("/v1/kv/servers/$this->leader_name/leader");
        $leader = $result['body'];
        if($leader=='true'){//是leader
            get_instance()->sendToAllWorks(SwooleMarco::CONSUL_SERVICES_LEADER_CHANGE,true,ConsulHelp::class."::leaderChange");
        }else{//不是leader
            get_instance()->sendToAllWorks(SwooleMarco::CONSUL_SERVICES_LEADER_CHANGE,false,ConsulHelp::class."::leaderChange");
        }
        Coroutine::startCoroutine([$this,'checkLeader'],[$index]);
    }

    /**
     * 定时器调用
     * @param int $index
     * @return \Generator|void
     */
    public function checkLeader($index=0)
    {
        if(!$this->config['consul_enable']){
            return;
        }
        $result = yield $this->consul->httpClient->setMethod('GET')
            ->setQuery(['index'=>$index])
            ->coroutineExecute("/v1/kv/servers/$this->leader_name/leader")->setTimeout(10*60*1000);
        $body = json_decode($result['body'],true)[0];
        $index = $result['headers']['x-consul-index'];
        if(!isset($body['Session']))//代表没有Leader
        {
            Coroutine::startCoroutine([$this,'leader'],[$index]);
        }else{
            Coroutine::startCoroutine([$this,'checkLeader'],[$index]);
        }
    }
}