<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-13
 * Time: 下午1:52
 */

namespace Server\Components\Consul;


use Server\Asyn\HttpClient\HttpClientPool;

class ConsulRest extends HttpClientPool
{
    private $service;
    private $context;

    /**
     * @param $service
     * @param $context
     * @return $this
     */
    public function init($service, $context)
    {
        $this->service = $service;
        $this->context = $context;
        return $this;
    }
    /**
     * @param $headers
     * @return $this
     */
    public function setHeaders($headers)
    {
        $this->httpClient->setHeaders($headers);
        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function addHeader($key,$value)
    {
        $this->httpClient->addHeader($key,$value);
        return $this;
    }

    /**
     * @param $data
     * @return $this
     */
    public function setData($data)
    {
        $this->httpClient->setData($data);
        return $this;
    }

    /**
     * @param $method
     * @return $this
     */
    public function setMethod($method)
    {
        $this->httpClient->setMethod($method);
        return $this;
    }

    /**
     * 设置get的query
     * @param array $query
     * @return $this
     */
    public function setQuery(array $query)
    {
        $this->httpClient->setQuery($query);
        return $this;
    }

    /**
     * @param $name
     * @param $arguments
     * @return \Server\Asyn\HttpClient\HttpClientRequestCoroutine
     */
    public function __call($name, $arguments)
    {
        $this->httpClient->addHeader('rpc_request_id',$this->context['request_id']);
        return $this->httpClient->coroutineExecute("/$this->service/$name");
    }
}