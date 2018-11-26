<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-2
 * Time: 下午1:44
 */
namespace Server\Asyn\HttpClient;

use Server\CoreBase\SwooleException;
use Server\Memory\Pool;

class HttpClient
{
    protected $headers;
    protected $cookies;
    protected $data;
    protected $method;
    protected $addFiles;
    protected $client;
    protected $baseUrl;
    protected $query;
    protected $pool;

    public function __construct($pool, $baseUrl)
    {
        $this->pool = $pool;
        $this->baseUrl = $baseUrl;
        $this->reset();
    }

    private function reset()
    {
        $this->headers = [];
        $this->cookies = [];
        $this->data = null;
        $this->method = 'GET';
        $this->addFiles = [];
        $this->query = '';
    }

    /**
     * @param $headers
     * @return $this
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function addHeader($key,$value)
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * @param $cookies
     * @return $this
     */
    public function setCookies($cookies)
    {
        $this->cookies = $cookies;
        return $this;
    }

    /**
     * @param $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @param $method
     * @return $this
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * 设置get的query
     * @param array $query
     * @return $this
     */
    public function setQuery(array $query)
    {
        $this->query = http_build_query($query);
        return $this;
    }

    /**
     * @param string $path
     * @param string $name
     * @param string|null $filename
     * @param string|null $mimeType
     * @param int $offset
     * @return $this
     */
    public function addFile(string $path, string $name, string $filename = null,
                            string $mimeType = null, int $offset = 0)
    {
        $this->addFiles[] = [$path, $name, $filename, $mimeType, $offset];
        return $this;
    }

    /**
     * @param $path
     * @param $callBack
     */
    public function execute($path, $callBack)
    {
        $data = $this->toArray();
        $arr = parse_url($this->baseUrl);
        $scheme = $arr['scheme'];
        $host = $arr['host'];
        if ($scheme == "https") {
            $ssl = true;
            $port = 443;
        } else {
            $ssl = false;
            $port = 80;
        }
        if (array_key_exists('port', $arr)) {
            $port = $arr['port'];
        }
        if ($this->client == null) {
            swoole_async_dns_lookup($host, function ($host1, $ip) use ($path, $port, $ssl, $data, $callBack) {
                $this->client = new \swoole_http_client($ip, $port, $ssl);
                $this->client->set(['timeout' => -1]);
                $this->client->setMethod($data['method']);
                if (!empty($data['query'])) {
                    $path = $path . '?' . $data['query'];
                }
                $data['headers']['Host'] = $host1;
                $this->client->setHeaders($data['headers']);
                $this->client->setCookies($data['cookies']);

                if ($data['data'] != null) {
                    $this->client->setData($data['data']);
                }
                foreach ($data['addFiles'] as $addFile) {
                    $this->client->addFile(...$addFile);
                }
                $this->client->execute($path, function ($client) use ($callBack) {
                    $data['headers'] = $client->headers;
                    $data['body'] = $client->body;
                    $data['statusCode'] = $client->statusCode;
                    \co::call_user_func($callBack, $data);
                });
            });
        } else {
            $this->client->setMethod($data['method']);
            if (!empty($data['query'])) {
                $path = $path . '?' . $data['query'];
            }
            $data['headers']['Host'] = $host;
            $this->client->setHeaders($data['headers']);

            if (count($data['cookies']) != 0) {
                $this->client->setCookies($data['cookies']);
            }
            if ($data['data'] != null) {
                $this->client->setData($data['data']);
            }
            foreach ($data['addFiles'] as $addFile) {
                $this->client->addFile(...$addFile);
            }
            $this->client->execute($path, function ($client) use ($callBack) {
                $data['headers'] = $client->headers;
                $data['body'] = $client->body;
                $data['statusCode'] = $client->statusCode;
                \co::call_user_func($callBack, $data);
            });
        }
    }

    /**
     * 协程版执行
     * @param $path
     * @param callable|null $set
     * @return HttpClientRequestCoroutine
     * @throws SwooleException
     */
    public function coroutineExecute($path, callable $set = null)
    {
        if (empty($this->baseUrl)) {
            throw new SwooleException('httpClient not set baseUrl!');
        }
        $data = $this->toArray();
        $data['path'] = $path;
        $data['callMethod'] = 'execute';
        $this->reset();
        return Pool::getInstance()->get(HttpClientRequestCoroutine::class)->init($this->pool, $data, $set);
    }

    /**
     * @return mixed
     */
    protected function toArray()
    {
        $data['method'] = $this->method;
        $data['query'] = $this->query;
        $data['headers'] = $this->headers;
        $data['cookies'] = $this->cookies;
        $data['data'] = $this->data;
        $data['addFiles'] = $this->addFiles;
        return $data;
    }

    /**
     * 协程版下载
     * @param string $path
     * @param string $filename
     * @param int $offset
     * @param callable|null $set
     * @return HttpClientRequestCoroutine
     * @throws SwooleException
     */
    public function coroutineDownload(string $path, string $filename, int $offset = 0, callable $set = null)
    {
        if (empty($this->baseUrl)) {
            throw new SwooleException('httpclient not set base url');
        }
        $data['path'] = $path;
        $data['filename'] = $filename;
        $data['offset'] = $offset;
        $data['callMethod'] = 'download';
        return Pool::getInstance()->get(HttpClientRequestCoroutine::class)->init($this->pool, $data, $set);
    }
}