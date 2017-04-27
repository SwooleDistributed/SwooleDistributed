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

    public $connect;
    protected $port;
    protected $host;
    protected $set;
    protected $client;
    /**
     * 因为这里是流rpc，会一直向服务器发请求，这里需要针对返回结果验证请求是否成功
     * @var array
     */
    private $command_backup;
    /**
     * @var IPack
     */
    protected $pack;
    protected $package_length_type_length;

    public function __construct($config, $config_name, $connect)
    {
        parent::__construct($config);
        $this->connect = $connect;
        $this->set = $this->config->get("tcpClient.$config_name.set", [
            'open_length_check' => 1,
            'package_length_type' => 'N',
            'package_length_offset' => 0,       //第N个字节是包长度的值
            'package_body_offset' => 0,       //第几个字节开始计算长度
            'package_max_length' => 2000000,  //协议最大长度
        ]);
        $this->package_length_type_length = strlen(pack($this->set['package_length_type'], 1));
        //pack class
        $pack_class_name = "app\\Pack\\" . $this->config['tcpClient'][$config_name]['pack_tool'];
        if (class_exists($pack_class_name)) {
            $this->pack = new $pack_class_name;
        } else {
            $pack_class_name = "Server\\Pack\\" . $this->config['tcpClient'][$config_name]['pack_tool'];
            if (class_exists($pack_class_name)) {
                $this->pack = new $pack_class_name;
            } else {
                throw new SwooleException("class {$this->config['server'][$config_name]['pack_tool']} is not exist.");
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
            $this->command_backup[$data['token']] = $data;
            $data['rpc_token'] = $data['token'];
            $token = $data['token'];
            unset($data['token']);
            $oneway = $data['oneway'];
            unset($data['oneway']);
            $data = $this->encode($this->pack->pack($data));
            $this->client->send($data);
            if ($oneway) {
                $result['token'] = $token;
                $result['result'] = null;
                unset($this->command_backup[$token]);
                $this->distribute($result);
            }
        }
    }

    /**
     * 准备一个httpClient
     */
    public function prepareOne()
    {
        $client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $client->set($this->set);
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
     * @param bool $oneway
     * @return TcpClientRequestCoroutine
     */
    public function coroutineSend($send, $oneway = false)
    {
        return Pool::getInstance()->get(TcpClientRequestCoroutine::class)->init($this, $send, $oneway);
    }

    /**
     * 帮助去构建一个SD的请求结构体
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
}