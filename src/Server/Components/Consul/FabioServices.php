<?php
/**
 * Created by PhpStorm.
 * User: Xavier
 * Date: 18-1-25
 * Time: 下午2:26
 */

namespace Server\Components\Consul;


use Server\CoreBase\SwooleException;

class FabioServices
{
    private static $instance;
    private $http_services;
    private $tcp_services;
    public function __construct()
    {
        self::$instance = $this;
        $this->config = get_instance()->config;
        $address=isset($this->config['fabio']['address'])?$this->config['fabio']['address']:'127.0.0.1';
        $http_port=isset($this->config['fabio']['http_port'])?$this->config['fabio']['http_port']:2345;
        $tcp_port=isset($this->config['fabio']['tcp_port'])?$this->config['fabio']['tcp_port']:1234;
        $this->http_services = new FabioRest($this->config, "http://$address:$http_port");
        $this->tcp_services = new FabioRpc($this->config, 'consul',"$address:$tcp_port");
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            new FabioServices();
        }
        return self::$instance;
    }

    /**
     * @param $serviceName
     * @param $context
     * @return ConsulRest
     * @throws SwooleException
     */
    public function getRESTService($serviceName, $context)
    {
        return $this->http_services->init($serviceName, $context);
    }

    /**
     * @param $serviceName
     * @param $context
     * @return ConsulRpc
     * @throws SwooleException
     */
    public function getRPCService($serviceName, $context)
    {
        return $this->tcp_services->init($serviceName, $context);
    }
}