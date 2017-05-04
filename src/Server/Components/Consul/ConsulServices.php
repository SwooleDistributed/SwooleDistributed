<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-9
 * Time: 下午2:26
 */

namespace Server\Components\Consul;


use Server\Asyn\HttpClient\HttpClientPool;
use Server\CoreBase\SwooleException;
use Server\SwooleMarco;

class ConsulServices
{
    private static $instance;
    private $http_services;
    private $tcp_services;
    private $config;
    private $httpClientPool;

    public function __construct()
    {
        self::$instance = $this;
        $this->http_services = [];
        $this->tcp_services = [];
        $this->config = get_instance()->config;
        $this->httpClientPool = new HttpClientPool($this->config, "http://0.0.0.0:8500");
    }

    /**
     * 服务器启动后正常情况是会超时的，因为consul服务器还未完全启动好，这地方只是为了reload的时候不会丢失而做的请求。。
     * 开始健康检查
     */
    public function serviceHealthCheck()
    {
        $watches = $this->config->get('consul.watches', []);
        foreach ($watches as $watch) {
            $result = yield $this->httpClientPool->httpClient->setQuery(['passing' => true])->coroutineExecute('/v1/health/service/' . $watch)->setTimeout(1000)->noException();
            if ($result != null) {
                $data = $watch . "@" . $result['body'];
                get_instance()->sendToAllWorks(SwooleMarco::CONSUL_SERVICES_CHANGE, $data, ConsulHelp::class . "::getMessgae");
            } else {
                break;
            }
        }
    }

    public static function getInstance()
    {
        if (self::$instance == null) {
            new ConsulServices();
        }
        return self::$instance;
    }

    /**
     * 一次只会有一个服务变更
     * 更新服务列表
     * @param $serviceName
     * @param $checks
     */
    public function updateServies($serviceName, $checks)
    {
        if (get_instance()->isTaskWorker()) return;
        if (!array_key_exists($serviceName, $this->tcp_services)) {
            $this->tcp_services[$serviceName] = [];
        }
        if (!array_key_exists($serviceName, $this->http_services)) {
            $this->http_services[$serviceName] = [];
        }
        $nodes = json_decode($checks, true);
        //代表服务不可用,全部移除
        if (count($nodes) == 0) {
            foreach ($this->http_services[$serviceName] as $address => $pool) {
                $this->removeServices($serviceName, $address, "http");
            }
            foreach ($this->tcp_services[$serviceName] as $address => $pool) {
                $this->removeServices($serviceName, $address, "tcp");
            }
            return;
        }
        //先找有没有增加
        foreach ($nodes as $node) {
            $service = $node['Service'];
            $address = $service['Address'];
            $tags = $service['Tags']??[];
            $port = $service['Port'];
            if (empty($address)) {
                $address = $node['Node']['Address'];
            }
            if (in_array('tcp', $tags)) {
                if (!array_key_exists($address, $this->tcp_services[$serviceName])) {
                    $this->addServices($serviceName, $address, $port, "tcp");
                }
            }
            if (in_array('http', $tags)) {
                if (!array_key_exists($address, $this->http_services[$serviceName])) {
                    $this->addServices($serviceName, $address, $port, "http");
                }
            }

        }
        //再找有没有减少
        //先看tcp
        $migrates = [];
        foreach ($this->tcp_services[$serviceName] as $address => $pool) {
            $find = false;
            foreach ($nodes as $node) {
                $_service = $node['Service'];
                $_address = $_service['Address'];
                $tags = $_service['Tags'];
                if (in_array('tcp', $tags) && $address == $_address) {
                    $find = true;
                    break;
                }
            }
            if (!$find) {
                $_migrates = $this->removeServices($serviceName, $address, "tcp");
                foreach ($_migrates as $migrate) {
                    $migrates[] = $migrate;
                }
            }
        }
        //处理下迁移问题，将命令迁移到其他可用的连接中
        while (count($migrates) != 0) {
            foreach ($this->tcp_services[$serviceName] as $address => $pool) {
                if (count($migrates) == 0) {
                    break;
                }
                $pool->migrates(array_shift($migrates));
            }
        }
        //再看http
        $migrates = [];
        foreach ($this->http_services[$serviceName] as $address => $pool) {
            $find = false;
            foreach ($nodes as $node) {
                $_service = $node['Service'];
                $_address = $_service['Address'];
                $tags = $_service['Tags'];
                if (in_array('http', $tags) && $address == $_address) {
                    $find = true;
                    break;
                }
            }
            if (!$find) {
                $_migrates = $this->removeServices($serviceName, $address, "http");
                foreach ($_migrates as $migrate) {
                    $migrates[] = $migrate;
                }
            }
        }
        //处理下迁移问题，将命令迁移到其他可用的连接中
        while (count($migrates) != 0) {
            foreach ($this->http_services[$serviceName] as $address => $pool) {
                if (count($migrates) == 0) {
                    break;
                }
                $pool->migrates(array_shift($migrates));
            }
        }
    }

    /**
     * 添加一个服务
     * @param $name
     * @param $address
     * @param $port
     * @param $type
     */
    private function addServices($name, $address, $port, $type)
    {
        switch ($type) {
            case "tcp":
                $config_name = 'consul_'.$name;
                if(!$this->config->has("tcpClient.$config_name")){
                    $config_name = 'consul';
                }
                $this->tcp_services[$name][$address] = new ConsulRpc($this->config, $config_name, "$address:$port");
                if(get_instance()->server->worker_id==0) {
                    print_r("发现$name($address:$port) TCP服务，应用配置$config_name\n");
                }
                break;
            case "http":
                $this->http_services[$name][$address] = new ConsulRest($this->config, "http://$address:$port");
                if(get_instance()->server->worker_id==0) {
                    print_r("发现$name($address:$port) HTTP服务\n");
                }
                break;
        }
    }

    /**
     * 移除一个服务
     * @param $name
     * @param $address
     * @param $type
     * @return mixed
     */
    private function removeServices($name, $address, $type)
    {
        switch ($type) {
            case "tcp":
                $migrate = $this->tcp_services[$name][$address]->destroy();
                unset($this->tcp_services[$name][$address]);
                break;
            case "http":
                $migrate = $this->http_services[$name][$address]->destroy();
                unset($this->http_services[$name][$address]);
                break;
        }
        return $migrate;
    }

    /**
     * @param $serviceName
     * @return ConsulRest
     * @throws SwooleException
     */
    public function getRESTService($serviceName, $context)
    {
        if (!array_key_exists($serviceName, $this->http_services)) {
            throw new SwooleException("$serviceName No service available");
        }
        if (count($this->http_services[$serviceName]) == 0) {
            throw new SwooleException("$serviceName No service available");
        }
        $address = array_rand($this->http_services[$serviceName]);
        return $this->http_services[$serviceName][$address]->init($serviceName, $context);
    }

    /**
     * @param $serviceName
     * @return ConsulRpc
     * @throws SwooleException
     */
    public function getRPCService($serviceName, $context)
    {
        if (!array_key_exists($serviceName, $this->tcp_services)) {
            throw new SwooleException("$serviceName No service available");
        }
        if (count($this->tcp_services[$serviceName]) == 0) {
            throw new SwooleException("$serviceName No service available");
        }
        $address = array_rand($this->tcp_services[$serviceName]);
        return $this->tcp_services[$serviceName][$address]->init($serviceName, $context);
    }
}