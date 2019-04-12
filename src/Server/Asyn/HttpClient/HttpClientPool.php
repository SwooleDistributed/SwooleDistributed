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
    protected $pool_chan;
    public $baseUrl;
    protected $name;
    protected $host;
    protected $data;
    /**
     * 一直向服务器发请求，这里需要针对返回结果验证请求是否成功
     * @var array
     */
    private $command_backup;
    protected $httpClient_max_count;

    public function __construct($config, $baseUrl)
    {
        parent::__construct($config);
        if (get_instance()->isTaskWorker()) return;
        $this->baseUrl = $baseUrl;
        if (empty($this->baseUrl)) {
            throw new SwooleException('httpClient not set baseUrl!');
        }
        $this->client_max_count = $this->config->get('httpClient.asyn_max_count', 10);
        $this->data = [];
        $arr = parse_url($this->baseUrl);
        $scheme = $arr['scheme'];
        $this->host = $arr['host'];
        if ($scheme == "https") {
            $this->data['ssl'] = true;
            $this->data['port'] = 443;
        } else {
            $this->data['ssl'] = false;
            $this->data['port'] = 80;
        }
        if (array_key_exists('port', $arr)) {
            $this->data['port'] = $arr['port'];
        }
        $this->pool_chan = new \chan($this->client_max_count);
        $this->data['ip'] = \Swoole\Coroutine::gethostbyname($this->host);
        for ($i = 0; $i < $this->client_max_count; $i++) {
            $client = new \Swoole\Coroutine\Http\Client($this->data['ip'], $this->data['port'], $this->data['ssl']);
            $client->id = $i;
            $this->pushToPool($client);
        }
        $this->httpClient = new HttpClient($this, $baseUrl);
        secho("HttpClientPool", "已初始化完HttpClientPool[$baseUrl]");
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
        return self::AsynName . ":" . $this->name;
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
        $client = $this->pool_chan->pop();
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
                $client->execute($path);
                if ($client->statusCode < 0) {
                    return;
                }
                //分发消息
                $data['token'] = $token;
                unset($this->command_backup[$token]);
                $data['result']['headers'] = $client->headers;
                $data['result']['body'] = $client->body;
                $data['result']['statusCode'] = $client->statusCode;
                $this->distribute($data);
                if (strtolower($client->headers['connection'] ?? 'keep-alive') == 'keep-alive') {//代表是keepalive可以直接回归
                    //回归连接
                    $this->pushToPool($client);
                } else {//需要延迟回归
                    $client->delay = true;
                }
                break;
            case 'download':
                $client->download($data['path'], $data['filename'], $data['offset']);
                //分发消息
                $data['token'] = $token;
                $data['result'] = $client->downloadFile;
                $this->distribute($data);
                //回归连接
                $this->pushToPool($client);
                break;
        }

    }

    public function pushToPool($client)
    {
        $this->pool_chan->push($client);
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

    public function setName($name)
    {
        $this->name = $name;
    }
}