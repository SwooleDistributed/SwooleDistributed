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
 * 连接池版
 * Class TcpClientPool
 * @package Server\Asyn\TcpClient
 */
class TcpClientPool extends AsynPool
{
    const AsynName = 'tcp_client';

    public $connect;
    protected $port;
    protected $host;
    protected $set;
    /**
     * @var IPack
     */
    protected $pack;
    protected $tcpClient_max_count;
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
        $this->client_max_count = $this->config->get('tcpClient.asyn_max_count', 10);
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
        $data['token'] = $this->addTokenCallback($callback);
        $data['send'] = $this->encode($this->pack->pack($send));
        $data['oneway'] = $oneway;
        $this->execute($data);
        return $data['token'];
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
        $client = $this->shiftFromPool($data);
        if ($client) {
            if (!$client->isConnected()) {
                unset($client);
                $this->client_count--;
                $this->prepareOne();
                $this->commands->push($data);
                return;
            }
            $client->token = $data['token'];
            $client->send($data['send']);
            //单向的返回null直接回收
            if ($data['oneway']??false) {
                $result['token'] = $data['token'];
                $result['result'] = null;
                $this->distribute($result);
                unset($client->token);
                $this->pushToPool($client);
            }
        }
    }

    /**
     * 准备一个httpClient
     */
    public function prepareOne()
    {
        if (parent::prepareOne()) {
            $client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
            $client->set($this->config->get('tcpClient.set', []));
            $client->on("connect", function ($cli) {
                $this->pushToPool($cli);
            });
            $client->on("receive", function ($cli, $recdata) {
                if (isset($cli->token)) {
                    $packdata = $this->pack->unPack($this->unEncode($recdata));
                    $data['token'] = $cli->token;
                    $data['result'] = $packdata;
                    $this->distribute($data);
                    unset($cli->token);
                    $this->pushToPool($cli);
                }
            });
            $client->on("error", function ($cli) {
                $cli->close();
            });
            $client->on("close", function ($cli) {

            });
            $client->connect($this->host, $this->port);
        }
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
     * 设置path
     * @param $path
     * @param $data
     * @return mixed
     */
    public function setPath($path, &$data)
    {
        $data['path'] = $path;
        return $data;
    }

    /**
     * 销毁Client
     * @param $client
     */
    protected function destoryClient($client)
    {
        $client->close();
    }

}