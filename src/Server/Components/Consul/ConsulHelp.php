<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-9
 * Time: 下午12:10
 */

namespace Server\Components\Consul;

use Server\CoreBase\PortManager;

class ConsulHelp
{
    protected static $is_leader = null;
    protected static $session_id;

    public static function getMessgae($message)
    {
        list($name, $data) = explode('@', $message);
        ConsulServices::getInstance()->updateServies($name, $data);
    }

    /**
     * 格式化consul模板，输出配置文件
     */
    public static function jsonFormatHandler()
    {
        $config = get_instance()->config->get('consul');
        $newConfig['node_name'] = $config['node_name'];
        $newConfig['start_join'] = $config['start_join'];
        $newConfig['data_dir'] = $config['data_dir'];
        $newConfig['bind_addr'] = $config['bind_addr'];
        $path = BIN_DIR . "/start_service_handle.php";
        if (array_key_exists('watches', $config)) {
            foreach ($config['watches'] as $watch) {
                $newConfig['watches'][] = ['type' => 'service', 'passingonly' => true, 'service' => $watch, 'handler' => "php $path $watch"];
            }
        }
        if (array_key_exists('services', $config)) {
            foreach ($config['services'] as $service) {
                list($service_name, $service_port) = explode(":", $service);
                $service_port = (int) $service_port;
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

    /**
     * 开启进程
     */
    public static function startProcess()
    {
        if (get_instance()->config->get('consul.enable', false)) {
            self::jsonFormatHandler();
            $consul_process = new \swoole_process(function ($process) {
                if (!isDarwin()) {
                    $process->name('SWD-CONSUL');
                }
                $process->exec(BIN_DIR . "/exec/consul", ['agent', '-ui', '-config-dir', BIN_DIR . '/exec/consul.d']);
            }, false, false);
            get_instance()->server->addProcess($consul_process);
        }
    }

    /**
     * leader变更
     * @param $is_leader
     */
    public static function leaderChange($is_leader)
    {
        if (get_instance()->server->worker_id == 0) {
            if ($is_leader !== self::$is_leader) {
                if ($is_leader) {
                    print_r("Leader变更，被选举为Leader\n");
                } else {
                    print_r("Leader变更，本机不是Leader\n");
                }
            }
        }
        self::$is_leader = $is_leader;
    }

    /**
     * @param $session_id
     */
    public static function setSession($session_id)
    {
        self::$session_id = $session_id;
    }

    /**
     * 是否是leader
     * @return bool
     */
    public static function isLeader()
    {
        if (self::$is_leader == null) {
            return false;
        }
        return self::$is_leader;
    }

    public static function getSessionID()
    {
        return self::$session_id;
    }

}