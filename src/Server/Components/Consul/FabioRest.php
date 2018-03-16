<?php
/**
 * Created by PhpStorm.
 * User: Xavier
 * Date: 18-1-25
 * Time: 下午2:26
 */

namespace Server\Components\Consul;


use Server\Asyn\HttpClient\HttpClientPool;

class FabioRest extends HttpClientPool
{
    public function __construct($pool, $baseUrl)
    {
        parent::__construct($pool, $baseUrl);
        $this->baseUrl=$baseUrl;
        $this->httpClient->setMethod('POST');
    }
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
     * @param $method
     * @return $this
     */
    public function setMethod($method)
    {
        $this->httpClient->setMethod($method);
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