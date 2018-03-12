<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午3:51
 */

namespace app\Controllers;

use app\Actors\TestActor;
use app\Models\TestModel;
use Server\Asyn\Mysql\Miner;
use Server\Asyn\TcpClient\SdTcpRpcPool;
use Server\Asyn\TcpClient\TcpClientPool;
use Server\Asyn\TcpClient\TcpClientRequestCoroutine;
use Server\Components\CatCache\CatCacheRpcProxy;
use Server\Components\CatCache\TimerCallBack;
use Server\Components\Consul\ConsulServices;
use Server\Components\Event\EventCoroutine;
use Server\Components\Event\EventDispatcher;
use Server\CoreBase\Actor;
use Server\CoreBase\ChildProxy;
use Server\CoreBase\Controller;
use Server\Memory\Cache;
use Server\Memory\Lock;
use Server\Tasks\TestTask;

class TestController extends Controller
{
    /**
     * @var TestTask
     */
    public $testTask;

    /**
     * @var TestModel
     */
    public $testModel;

    /**
     * @var SdTcpRpcPool
     */
    public $sdrpc;

    /**
     * @var TcpClientPool
     */
    public $rpc;

    public function __construct($proxy = ChildProxy::class)
    {
        parent::__construct($proxy);
        $this->rpc = get_instance()->getAsynPool("RPC");
    }

    public function http_tcpClient()
    {
        $data = ['controller_name' => "TestController", "method_name" => "testTcp", "data" => "test"];
        $this->rpc->setPath("TestController/testTcp", $data);
        $result = $this->rpc->coroutineSend($data);
        $this->http_output->end($result);
    }

    public function http_map_add()
    {
        $cache = Cache::getCache('TestCache');
        $cache->addMap('123');
        $this->http_output->end($cache->getAllMap());
    }

    public function http_tcp()
    {
        $this->sdrpc = get_instance()->getAsynPool('RPC');
        $data = $this->sdrpc->helpToBuildSDControllerQuest($this->context, 'MathService', 'add');
        $data['params'] = [1, 2];
        $result = $this->sdrpc->coroutineSend($data);
        $this->http_output->end($result);
    }

    public function http_ex()
    {
        throw new \Exception("test");
    }

    public function http_error()
    {
        $a = [];
        $a[1];
    }

    public function http_testModelMysql()
    {
        $model = $this->loader->model(TestModel::class, $this);
        $result = $model->testMysql();
        $this->http_output->end($result);
    }

    public function http_testModelRedis()
    {
        $model = $this->loader->model(TestModel::class, $this);
        $result = $model->testRedis();
        $this->http_output->end($result);
    }

    /**
     * tcp的测试
     */
    public function testTcp()
    {
        var_dump($this->client_data->data);
        $this->send($this->client_data->data);
    }

    public function add()
    {
        $max = $this->client_data->max;
        if (empty($max)) {
            $max = 100;
        }
        $sum = 0;
        for ($i = 0; $i < $max; $i++) {
            $sum += $i;
        }
        $this->send($max);
    }

    public function http_testContext()
    {
        $this->getContext()['test'] = 1;
        $this->testModel = $this->loader->model('TestModel', $this);
        $this->testModel->contextTest();
        $this->http_output->end($this->getContext());
    }

    /**
     * mysql 事务协程测试
     */
    public function http_mysql_begin_coroutine_test()
    {
        $this->db->begin(function () {
            $result = $this->db->select("*")->from("account")->query();
            var_dump($result['client_id']);
            $result = $this->db->select("*")->from("account")->query();
            var_dump($result['client_id']);
        });
        $this->http_output->end(1);
    }


    /**
     * 绑定uid
     */
    public function bind_uid()
    {
        $this->bindUid($this->client_data->data, true);
    }

    /**
     * 效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function efficiency_test()
    {
        $data = $this->client_data->data;
        $this->sendToUid(mt_rand(1, 100), $data);
    }

    /**
     * 效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function efficiency_test2()
    {
        $data = $this->client_data->data;
        $this->send($data);
    }

    /**
     * mysql效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function http_smysql()
    {
        $result = $this->db->select('*')->from('account')->limit(1)->query();
        $this->http_output->end($result, false);
    }

    public function http_amysql()
    {
        $result = get_instance()->getMysql()->select('*')->from('account')->where('uid', 10004)->pdoQuery();
        $this->http_output->end($result, false);
    }

    /**
     * 获取mysql语句
     */
    public function http_mysqlStatement()
    {
        $value = $this->mysql_pool->dbQueryBuilder->insertInto('account')->intoColumns(['uid', 'static'])->intoValues([[36, 0], [37, 0]])->getStatement(true);
        $this->http_output->end($value);
    }

    /**
     * http测试
     */
    public function http_test()
    {
        $max = $this->http_input->get('max');
        if (empty($max)) {
            $max = 100;
        }
        $sum = 0;
        for ($i = 0; $i < $max; $i++) {
            $sum += $i;
        }
        $this->http_output->end($sum);
    }

    public function http_redirect()
    {
        $this->redirectController('TestController', 'test');
    }

    /**
     * health
     */
    public function http_health()
    {
        $this->http_output->end('1');
    }

    /**
     * http redis 测试
     */
    public function http_redis()
    {
        $result = $this->redis_pool->getCoroutine()->get('testroute');
        $this->http_output->end($result, false);
    }

    /**
     * http redis 测试
     */
    public function http_redis2()
    {
        $this->redis_pool->getRedisPool()->get('testroute', function () {
            $this->http_output->end(1, false);
        });
    }

    /**
     * http redis 测试
     */
    public function http_setRedis()
    {
        $result = $this->redis_pool->getCoroutine()->set('testroute', 21, ["XX", "EX" => 10]);
        $this->http_output->end($result);
    }

    /**
     * http 同步redis 测试
     */
    public function http_aredis()
    {
        $value = get_instance()->getRedis()->get('testroute');
        $this->http_output->end($value, false);
    }

    /**
     * html测试
     */
    public function http_html_test()
    {
        $template = $this->loader->view('server::welcome');
        $this->http_output->end($template);
    }

    public function http_getAllTask()
    {
        $messages = get_instance()->getServerAllTaskMessage();
        $this->http_output->end(json_encode($messages));
    }

    /**
     * @return boolean
     */
    public function isIsDestroy()
    {
        return $this->is_destroy;
    }

    public function http_lock()
    {
        $lock = new Lock('test1');
        $result = $lock->coroutineLock();
        $this->http_output->end($result);
    }

    public function http_unlock()
    {
        $lock = new Lock('test1');
        $result = $lock->coroutineUnlock();
        $this->http_output->end($result);
    }

    public function http_destroylock()
    {
        $lock = new Lock('test1');
        $lock->destroy();
        $this->http_output->end(1);
    }

    public function http_testTask()
    {
        $testTask = $this->loader->task(TestTask::class, $this);
        $result = $testTask->test();
        $this->http_output->end($result);
    }

    public function http_testConsul()
    {
        $rest = ConsulServices::getInstance()->getRESTService('MathService', $this->context);
        $rest->setQuery(['one' => 1, 'two' => 2]);
        $reuslt = $rest->add();
        $this->http_output->end($reuslt['body']);
    }

    public function http_testConsul2()
    {
        $rest = ConsulServices::getInstance()->getRPCService('MathService', $this->context);
        $reuslt = $rest->add(1, 2);
        $this->http_output->end($reuslt);
    }

    public function http_testConsul3()
    {
        $rest = ConsulServices::getInstance()->getRPCService('MathService', $this->context);
        $reuslt = $rest->call('sum', [10000000], false, function (TcpClientRequestCoroutine $clientRequestCoroutine) {
            $clientRequestCoroutine->setTimeout(1000);
            $clientRequestCoroutine->setDowngrade(function () {
                return 123;
            });
        });
        $this->http_output->end($reuslt);
    }

    public function http_testRedisLua()
    {
        $value = $this->redis_pool->getCoroutine()->evalSha(getLuaSha1('sadd_from_count'), ['testlua', 100], 2, [1, 2, 3]);
        $this->http_output->end($value);
    }

    public function http_testTaskStop()
    {
        $task = $this->loader->task('TestTask', $this);
        $task->testStop();
    }

    public function http_echo()
    {
        $this->http_output->end(123, false);
    }

    /**
     * 事件处理
     */
    public function http_getEvent()
    {
        $data = EventDispatcher::getInstance()->addOnceCoroutine('unlock', function (EventCoroutine $e) {
            $e->setTimeout(10000);
        });
        //这里会等待事件到达，或者超时
        $this->http_output->end($data);
    }

    public function http_sendEvent()
    {
        EventDispatcher::getInstance()->dispatch('unlock', 'hello block');
        $this->http_output->end('ok');
    }

    public function http_testWhile()
    {
        $this->testModel = $this->loader->model('TestModel', $this);
        $this->testModel->testWhile();
        $this->http_output->end(1);
    }

    public function http_testMysqlRaw()
    {
        $selectMiner = $this->mysql_pool->dbQueryBuilder->select('*')->from('account');
        $selectMiner = $selectMiner->where('', '(status = 1 and dec in ("ss", "cc")) or name = "kk"', Miner::LOGICAL_RAW);
        $this->http_output->end($selectMiner->getStatement(false));
    }

    public function http_getAllUids()
    {
        $uids = get_instance()->coroutineGetAllUids();
        $this->http_output->end($uids);
    }

    public function http_testSC1()
    {
        $result = CatCacheRpcProxy::getRpc()->offsetExists('test.bc');
        $this->http_output->end($result, false);
    }

    public function http_testSC2()
    {
        unset(CatCacheRpcProxy::getRpc()['test.a']);
        $this->http_output->end(1, false);
    }


    public function http_testSC3()
    {
        CatCacheRpcProxy::getRpc()['test.a'] = ['a' => 'a', 'b' => [1, 2, 3]];
        $this->http_output->end(1, false);
    }

    public function http_testSC4()
    {
        $result = CatCacheRpcProxy::getRpc()->offsetGet('test');
        $this->http_output->end($result, false);
    }

    public function http_testSC5()
    {
        $result = CatCacheRpcProxy::getRpc()->getAll();
        $this->http_output->end($result, false);
    }

    public function http_testTimerCallBack()
    {
        $token = TimerCallBack::addTimer(2, TestModel::class, 'testTimerCall', [123]);
        $this->http_output->end($token);
    }

    public function http_testActor()
    {
        Actor::create(TestActor::class, "Test1");
        Actor::create(TestActor::class, "Test2");
        $this->http_output->end(123);
    }

    public function http_testActor2()
    {
        $rpc = Actor::getRpc("Test2");
        $beginid = $rpc->beginCo(function () use ($rpc) {
            $result = $rpc->test1();
            $result = $rpc->test2();
            //var_dump($result);
            $result = $rpc->test3();
        });
        $this->http_output->end(1);
    }

}