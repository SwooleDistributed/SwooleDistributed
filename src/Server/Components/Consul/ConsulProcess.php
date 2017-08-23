<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-14
 * Time: 下午2:55
 */

namespace Server\Components\Consul;

use Server\Components\Process\Process;
use Server\CoreBase\PortManager;
use Server\CoreBase\SwooleException;
use Server\CoreBase\TimerTask;

class ConsulProcess extends Process
{
    /**
     * @param $process
     * @throws SwooleException
     */
    public function start($process)
    {
        parent::start($process);
        $this->jsonFormatHandler();
        if (!is_file(BIN_DIR . "/exec/consul")) {
            echo("consul没有安装,请下载最新的consul安装至bin/exec目录,或者在config/consul.php中取消使能\n");
            get_instance()->server->shutdown();
            return;
        }
        $process->exec(BIN_DIR . "/exec/consul", ['agent', '-ui', '-config-dir', BIN_DIR . '/exec/consul.d']);
    }

    /**
     * 格式化consul模板，输出配置文件
     */
    public function jsonFormatHandler()
    {
        $config = get_instance()->config->get('consul');
        $newConfig['node_name'] = $config['node_name'];
        $newConfig['start_join'] = $config['start_join'];
        $newConfig['data_dir'] = $config['data_dir'];
        $newConfig['bind_addr'] = $config['bind_addr'];

        if (array_key_exists('services', $config)) {
            foreach ($config['services'] as $service) {
                list($service_name, $service_port) = explode(":", $service);
                $service_port = (int)$service_port;
                $port_type = get_instance()->portManager->getPortType($service_port);
                switch ($port_type) {
                    case PortManager::SOCK_TCP:
                    case PortManager::SOCK_TCP6:
                        $newConfig['services'][] = [
                            'id' => "Tcp_$service_name",
                            'name' => $service_name,
                            'address' => $config['bind_addr'],
                            'port' => $service_port,
                            'tags' => ['tcp'],
                            'check' => [
                                'name' => 'status',
                                'tcp' => "localhost:$service_port",
                                'interval' => "10s",
                                'timeout' => "1s"
                            ]];
                        break;
                    case PortManager::SOCK_HTTP:
                        $newConfig['services'][] = [
                            'id' => "Http_$service_name",
                            'name' => $service_name,
                            'address' => $config['bind_addr'],
                            'port' => $service_port,
                            'tags' => ['http'],
                            'check' => [
                                'name' => 'status',
                                'http' => "http://localhost:$service_port/$service_name/_consul_health",
                                'interval' => "10s",
                                'timeout' => "1s"
                            ]];
                        break;
                }
            }
        }
        file_put_contents(BIN_DIR . "/exec/consul.d/consul_config.json", json_encode($newConfig));
    }

}