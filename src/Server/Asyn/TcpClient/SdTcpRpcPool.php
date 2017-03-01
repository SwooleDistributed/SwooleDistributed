<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-2-27
 * Time: 上午10:13
 */

namespace Server\Asyn\TcpClient;


use Server\Asyn\AsynPool;
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

    protected $connect;
    protected $port;
    protected $host;
    protected $set;
    protected $client;
    /**
     * @var IPack
     */
    protected $pack;
    protected $package_length_type_length;

    public function __construct($config, $connect)
    {
        parent::__construct($config);
        $this->connect = $connect;
        $this->set = $this->config->get('tcpClient.set', [
            'open_length_check' => 1,
            'package_length_type' => 'N',
            'package_length_offset' => 0,       //第N个字节是包长度的值
            'package_body_offset' => 0,       //第几个字节开始计算长度
            'package_max_length' => 2000000,  //协议最大长度
        ]);
        $this->package_length_type_length = strlen(pack($this->set['package_length_type'], 1));
        //pack class
        $pack_class_name = "\\app\\Pack\\" . $this->config['tcpClient']['pack_tool'];
        if (class_exists($pack_class_name)) {
            $this->pack = new $pack_class_name;
        } else {
            $pack_class_name = "\\Server\\Pack\\" . $this->config['tcpClient']['pack_tool'];
            if (class_exists($pack_class_name)) {
                $this->pack = new $pack_class_name;
            } else {
                throw new SwooleException("class {$this->config['server']['pack_tool']} is not exist.");
            }
        }
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
     * @internal param $data
     * @return int
     */
    public function send($send, $callback)
    {
        $send['rpc_token'] = $this->addTokenCallback($callback);
        $data = $this->encode($this->pack->pack($send));
        $this->execute($data);
        return $send['rpc_token'];
    }

    /**
     * 数据包编码
     * @param $buffer
     * @return string
     * @throws SwooleException
     */
    public function encode($buffer)
    {
        if ($this->set['open_length_check']??0 == 1) {
            $total_length = $this->package_length_type_length + strlen($buffer) - $this->set['package_body_offset'];
            return pack($this->set['package_length_type'], $total_length) . $buffer;
        } else if ($this->set['open_eof_check']??0 == 1) {
            return $buffer . $this->set['package_eof'];
        } else {
            throw new SwooleException("tcpClient won't support set");
        }
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
            $this->client->send($data);
        }
    }

    /**
     * 准备一个httpClient
     */
    public function prepareOne()
    {
        $client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $client->set($this->config->get('tcpClient.set', []));
        $client->on("connect", function ($cli) {
            $this->client = $cli;
            if (count($this->commands) > 0) {//有残留的任务
                $command = $this->commands->shift();
                $this->execute($command);
            }
        });
        $client->on("receive", function ($cli, $recdata) {
            $packdata = $this->pack->unPack($this->unEncode($recdata));
            if (isset($packdata->rpc_token)) {
                $data['token'] = $packdata->rpc_token;
                $data['result'] = $packdata->rpc_result;
                $this->distribute($data);
            }
        });
        $client->on("error", function ($cli) {
            $cli->close();
        });
        $client->on("close", function ($cli) {

        });
        $client->connect($this->host, $this->port);
    }

    /**
     * @param $buffer
     * @return string
     */
    public function unEncode($buffer)
    {
        if ($this->set['open_length_check']??0 == 1) {
            $data = substr($buffer, $this->package_length_type_length);
            return $data;
        } else if ($this->set['open_eof_check']??0 == 1) {
            $data = $buffer;
            return $data;
        }
    }

    /**
     * 协程的发送
     * @param $send
     * @return mixed
     */
    public function coroutineSend($send)
    {
        return Pool::getInstance()->get(TcpClientRequestCoroutine::class)->init($this, $send);
    }

    /**
     * 帮助去构建一个SD的请求结构体
     */
    public function helpToBuildSDControllerQuest($context, $controllerName, $method)
    {
        return ['path' => $controllerName . '/' . $method, 'controller_name' => $controllerName, 'method_name' => $method, 'rpc_request_id' => $context['request_id']];
    }

    /**
     * 销毁Client
     * @param $client
     */
    protected function destoryClient($client)
    {
        $client->close();
        $this->client = null;
    }
}