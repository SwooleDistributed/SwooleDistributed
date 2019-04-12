<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-3-19
 * Time: 上午10:07
 */

namespace Server\Controllers;

use Server\Asyn\HttpClient\HttpClientPool;
use Server\Components\CatCache\CatCacheRpcProxy;
use Server\CoreBase\Actor;
use Server\CoreBase\ChildProxy;
use Server\CoreBase\Controller;

class Test extends Controller
{
    /**
     * @var HttpClientPool
     */
    protected $GetIPAddressHttpClient;
    public function initialization($controller_name, $method_name)
    {
        parent::initialization($controller_name, $method_name);
        $this->GetIPAddressHttpClient = get_instance()->getAsynPool('GetIPAddress');
    }

    public function http_error()
    {
        throw new \Exception("test");
    }

    public function http_redis()
    {
        $this->redis->incr("test");
    }
    public function http_redis2()
    {
        $this->redis->set("test",1);
        $this->http_output->end($this->redis->get("test"));
    }

    public function http_mysql()
    {
        $this->http_output->end($this->db->select('count(*)')->from('t_patient')->query()->getResult());
    }

    public function http_httpclient()
    {
        $ip = $this->http_input->server('remote_addr');
        $response = $this->GetIPAddressHttpClient->httpClient
            ->setQuery(['format' => 'json', 'ip' => $ip])
            ->coroutineExecute('/iplookup/iplookup.php');
        $this->http_output->end($response);
    }

    public function http_catcache()
    {
        CatCacheRpcProxy::getRpc()['test.a'] = ['a' => 'a', 'b' => [1, 2, 3]];
        $this->http_output->end(1, false);
    }
    public function http_catcache2()
    {
        //$result = CatCacheRpcProxy::getRpc()['test'];协程不支持这样
        $result = CatCacheRpcProxy::getRpc()->offsetGet('test');
        $this->http_output->end($result, false);
    }
    public function http_createActor()
    {
        Actor::create(TestActor::class, "test");
    }

    public function http_actor()
    {
        $a = Actor::getRpc("test");
        $a->test();
    }

    public function login($uid)
    {
        $this->bindUid($uid);
        $this->send("ok");
    }

    public function mySub($topic)
    {
        $this->addSub($topic);
        $this->send("ok");
    }

    public function myPub($topic)
    {
        $this->sendPub($topic, "hello");
        $this->send("ok");
    }
}
