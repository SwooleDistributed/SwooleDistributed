<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-2-27
 * Time: 上午10:13
 */

namespace Server\Asyn\HttpClient;


use Server\Asyn\AsynPool;
use Server\CoreBase\SwooleException;

class HttpClientPool extends AsynPool
{
    const AsynName = 'http_client';
    /**
     * @var HttpClient
     */
    public $httpClient;
    public $baseUrl;
    protected $host;
    /**
     * 一直向服务器发请求，这里需要针对返回结果验证请求是否成功
     * @var array
     */
    private $command_backup;
    protected $httpClient_max_count;

    public function __construct($config, $baseUrl)
    {
        parent::__construct($config);
        $this->baseUrl = $baseUrl;
        $this->httpClient = new HttpClient($this, $baseUrl);
        $this->client_max_count = $this->config->get('httpClient.asyn_max_count', 10);
    }

    /**
     * 获取同步
     * @throws SwooleException
     */
    public function getSync()
    {
        throw new SwooleException('暂时没有HttpClient的同步方法');
    }

    /**
     * @return string
     */
    public function getAsynName()
    {
        return self::AsynName . ":" . $this->baseUrl;
    }

    /**
     * @param $data
     * @param $callback
     * @return int
     */
    public function call($data, $callback)
    {
        $data['token'] = $this->addTokenCallback($callback);
        $this->execute($data);
        return $data['token'];
    }

    /**
     * @param $data
     */
    public function execute($data)
    {
        $client = $this->shiftFromPool($data);
        if ($client) {
            $token = $data['token'];
            $this->command_backup[$token] = $data;
            switch ($data['callMethod']) {
                case 'execute':
                    $client->setMethod($data['method']);
                    $path = $data['path'];
                    if (!empty($data['query'])) {
                        $path = $data['path'] . '?' . $data['query'];
                    }
                    $data['headers']['Host'] = $this->host;
                    $client->setHeaders($data['headers']);

                    if (count($data['cookies']) != 0) {
                        $client->setCookies($data['cookies']);
                    }
                    if ($data['data'] != null) {
                        $client->setData($data['data']);
                    }
                    foreach ($data['addFiles'] as $addFile) {
                        $client->addFile(...$addFile);
                    }
                    $client->execute($path, function ($client) use ($token) {
                        //分发消息
                        $data['token'] = $token;
                        unset($this->command_backup[$token]);
                        $data['result']['headers'] = $client->headers;
                        $data['result']['body'] = $client->body;
                        $data['result']['statusCode'] = $client->statusCode;
                        $this->distribute($data);
                        if(strtolower($client->headers['connection']??'keep-alive')=='keep-alive') {//代表是keepalive可以直接回归
                            //回归连接
                            $this->pushToPool($client);
                        }else{//需要延迟回归
                            $client->delay = true;
                        }
                    });
                    break;
                case 'download':
                    $client->download($data['path'], $data['filename'], function ($client) use ($token) {
                        //分发消息
                        $data['token'] = $token;
                        $data['result'] = $client->downloadFile;
                        $this->distribute($data);
                        //回归连接
                        $this->pushToPool($client);
                    }, $data['offset']);
                    break;
            }
        }
    }

    /**
     * 准备一个httpClient
     */
    public function prepareOne()
    {
        if (parent::prepareOne()) {
            $data = [];
            $arr = parse_url($this->baseUrl);
            $scheme = $arr['scheme'];
            $host = $arr['host'];
            if ($scheme == "https") {
                $data['ssl'] = true;
                $data['port'] = 443;
            } else {
                $data['ssl'] = false;
                $data['port'] = 80;
            }
            if (array_key_exists('port', $arr)) {
                $data['port'] = $arr['port'];
            }
            swoole_async_dns_lookup($host, function ($host, $ip) use (&$data) {
                $client = new \swoole_http_client($ip, $data['port'], $data['ssl']);
                $this->host = $host;
                $this->pushToPool($client);
                $client->on('close', function ($cli){
                    if(isset($cli->delay)) {
                        $this->pushToPool($cli);
                        unset($cli->delay);
                    }
                });
            });
        }
    }

    /**
     * 销毁Client
     * @param $client
     */
    protected function destoryClient($client)
    {
        $client->close();
    }

    /**
     * 销毁整个池子
     */
    public function destroy(&$migrate = [])
    {
        if ($this->command_backup != null) {
            foreach ($this->command_backup as $command) {
                $command['callback'] = $this->callBacks[$command['token']];
                $migrate[] = $command;
            }
        }
        $migrate = parent::destroy($migrate);
        $this->httpClient = null;
        $this->command_backup = null;
        return $migrate;
    }
}