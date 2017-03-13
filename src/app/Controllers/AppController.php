<?php
namespace app\Controllers;

use app\Models\AppModel;
use Server\Asyn\HttpClient\HttpClientPool;
use Server\Asyn\Redis\RedisAsynPool;
use Server\Asyn\TcpClient\SdTcpRpcPool;
use Server\Asyn\TcpClient\TcpClientPool;
use Server\CoreBase\Controller;

/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午3:51
 */
class AppController extends Controller
{
    /**
     * @var AppModel
     */
    public $AppModel;

    /**
     * @var RedisAsynPool
     */
    private $redis2;

    /**
     * @var HttpClientPool
     */
    private $httpClientPool;

    /**
     * @var TcpClientPool
     */
    private $tcpClientPool;
    /**
     * @var SdTcpRpcPool
     */
    private $rpc;

    /**
     * http测试使用2个redis
     */
    public function http_test()
    {
        yield $this->redis_pool->getCoroutine()->set('redispool', 11);
        //yield $this->redis2->getCoroutine()->set('redispool', 22);
        $result = yield $this->redis_pool->getCoroutine()->get('redispool');
        print_r($result);
        //$result = yield $this->redis2->getCoroutine()->get('redispool');
        //print_r($result);
        $this->http_output->end($this->AppModel->test());
    }

    public function http_test_task()
    {
        $AppTask = $this->loader->task('AppTask', $this);
        $AppTask->testTask();
        $AppTask->startTask(function ($serv, $task_id, $data) {
            $this->http_output->end($data);
        });
    }

    /**
     * 测试httpclient接口，断路器测试
     */
    public function http_httpClient()
    {
        $this->httpClientPool->httpClient->setQuery(['max' => 1000000]);
        $reuslt = yield $this->httpClientPool->httpClient->coroutineExecute('/TestController/test')->setTimeout(100)->setDowngrade(function () {
            return ['body' => 'test'];
        });
        $this->http_output->end($reuslt['body']);
    }

    /**
     * RPC调用SD的接口
     */
    public function http_tcpClient()
    {
        $sendData = ['controller_name' => 'TestController', 'method_name' => 'testTcp', 'data' => 'helloRPC'];
        $sendData = $this->tcpClientPool->setPath('TestController/testTcp', $sendData);
        $reuslt = yield $this->tcpClientPool->coroutineSend($sendData);
        $this->log('test');
        $this->http_output->end($reuslt);
    }

    /**
     * RPC调用SD的接口
     */
    public function http_rpc()
    {
        $sendData = $this->rpc->helpToBuildSDControllerQuest($this->getContext(), 'TestController', 'testTcp');
        $sendData['data'] = 'helloRPC';
        $reuslt = yield $this->rpc->coroutineSend($sendData);
        $this->http_output->end($reuslt);
    }

    protected function initialization($controller_name, $method_name)
    {
        parent::initialization($controller_name, $method_name);
        $this->AppModel = $this->loader->model('AppModel', $this);
        //$this->redis2 = get_instance()->getAsynPool('redis2');
        $this->httpClientPool = get_instance()->getAsynPool('httpClient');
        $this->tcpClientPool = get_instance()->getAsynPool('tcpClient');
        $this->rpc = get_instance()->getAsynPool('rpc');
    }
}