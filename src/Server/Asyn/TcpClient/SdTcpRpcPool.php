<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-2-27
 * Time: 上午10:13
 */

namespace Server\Asyn\TcpClient;


use Server\Asyn\AsynPool;
use Server\CoreBase\PortManager;
use Server\CoreBase\SwooleException;
use Server\Memory\Pool;
use Server\Pack\IPack;

/**
 * 主要用作SD的RPC，所以加上了rpc_token用于识别，这里并没有用到连接池！！
 * 如果和SD通讯推荐使用这个而不是TcpClientPool
 * Class TcpClientPool
 * @package Server\Asyn\TcpClient
 */
class SdTcpRpcPool extends AsynPool
{
    const AsynName = 'tcp_rpc_client';

    public $connect;
    protected $port;
    protected $host;
    protected $set;
    protected $client;
    protected $name;
    /**
     * 因为这里是流rpc，会一直向服务器发请求，这里需要针对返回结果验证请求是否成功
     * @var array
     */
    private $command_backup;
    /**
     * @var IPack
     */
    protected $pack;
    protected $ssl_enable;
    /**
     * SdTcpRpcPool constructor.
     * @param $config
     * @param $config_name
     * @param $connect
     * @throws SwooleException
     */
    public function __construct($config, $config_name, $connect)
    {
        parent::__construct($config);
        $this->connect = $connect;
        $this->pack = PortManager::createPack($this->config->get("tcpClient.$config_name.pack_tool"));
        $this->ssl_enable = $this->config->get("tcpClient.$config_name.ssl_enable", false);
        $this->set = $this->pack->getProbufSet();
        list($this->host, $this->port) = explode(':', $connect);
    }

    /**
     * 获取同步
     * @throws SwooleException
     */
    public function getSync()
    {
        throw new SwooleException('暂时没有TcpClient的同步方法');
    }

    /**
     * @return string
     */
    public function getAsynName()
    {
        return self::AsynName . ":" . $this->connect;
    }

    /**
     * @param $send
     * @param $callback
     * @param bool $oneway
     * @return int
     * @internal param $data
     */
    public function send($send, $callback, $oneway = false)
    {
        $send['token'] = $this->addTokenCallback($callback);
        $send['oneway'] = $oneway;
        $this->execute($send);
        return $send['token'];
    }


    /**
     * @param $data
     */
    public function execute($data)
    {
        if ($this->client == null) {//代表目前没有可用的连接
            $this->prepareOne();
            $this->commands->push($data);
        } else {
            if (!$this->client->isConnected()) {
                $this->client = null;
                $this->prepareOne();
                $this->commands->push($data);
                return;
            }
            $this->command_backup[$data['token']] = $data;
            $data['rpc_token'] = $data['token'];
            $token = $data['token'];
            unset($data['token']);
            $oneway = $data['oneway'];
            unset($data['oneway']);
            $data = $this->pack->pack($data);
            $this->client->send($data);
            if ($oneway) {
                $result['token'] = $token;
                $result['result'] = null;
                unset($this->command_backup[$token]);
                $this->distribute($result);
            }
            if (count($this->commands) > 0) {//有残留的任务
                $command = $this->commands->shift();
                $this->execute($command);
            }
        }
    }

    /**
     * 准备一个httpClient
     */
    public function prepareOne()
    {
        if ($this->ssl_enable) {
            $client = new \swoole_client(SWOOLE_SOCK_TCP | SWOOLE_SSL, SWOOLE_SOCK_ASYNC);
        } else {
            $client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        }
        $client->set($this->set);
        $client->on("connect", function ($cli) {
            $this->client = $cli;
            if (count($this->commands) > 0) {//有残留的任务
                $command = $this->commands->shift();
                $this->execute($command);
            }
        });
        $client->on("receive", function ($cli, $recdata) {
            try {
                $packdata = $this->pack->unPack($recdata);
            } catch (\Exception $e) {
                return null;
            }
            if (isset($packdata->rpc_token)) {
                $data['token'] = $packdata->rpc_token;
                $data['result'] = $packdata->rpc_result;
                unset($this->command_backup[$packdata->rpc_token]);
                $this->distribute($data);
            }
        });
        $client->on("error", function ($cli) {
            if ($cli->isConnected()) {
                $cli->close();
            } else {
                $this->client = null;
            }
        });
        $client->on("close", function ($cli) {
            $this->client = null;
        });
        $client->connect($this->host, $this->port);
    }

    /**
     * 协程的发送
     * @param $send
     * @param bool $oneway
     * @param callable|null $set
     * @return TcpClientRequestCoroutine
     */
    public function coroutineSend($send, $oneway = false, callable $set = null)
    {
        return Pool::getInstance()->get(TcpClientRequestCoroutine::class)->init($this, $send, $oneway, $set);
    }

    /**
     * 帮助去构建一个SD的请求结构体
     * @param $context
     * @param $controllerName
     * @param $method
     * @return array
     */
    public function helpToBuildSDControllerQuest($context, $controllerName, $method)
    {
        return ['path' => '/' . $controllerName . '/' . $method, 'controller_name' => $controllerName, 'method_name' => $method, 'rpc_request_id' => $context['request_id']];
    }

    /**
     * 超时时需要处理下
     * 销毁垃圾
     * @param $token
     */
    public function destoryGarbage($token)
    {
        unset($this->callBacks[$token]);
        $this->destoryClient($this->client);
    }

    /**
     * 销毁Client
     * @param $client
     */
    protected function destoryClient($client)
    {
        if ($client != null && $this->client->isConnected()) {
            $client->close();
        }
        $this->client = null;
    }

    public function destroy(&$migrate = [])
    {
        if ($this->command_backup != null) {
            foreach ($this->command_backup as $command) {
                $command['callback'] = $this->callBacks[$command['token']];
                $migrate[] = $command;
            }
        }
        $migrate = parent::destroy($migrate);
        $this->command_backup = null;
        $this->destoryClient($this->client);
        return $migrate;
    }

    public function setName($name)
    {
        $this->name = $name;
    }
}