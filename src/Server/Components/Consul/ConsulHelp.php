<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-9
 * Time: 下午12:10
 */

namespace Server\Components\Consul;

define('BIN_DIR', realpath(__DIR__ . "/../../../../bin/"));

class ConsulHelp
{
    protected static $is_leader = null;
    protected static $session_id;
    public static function getMessgae($message)
    {
        list($name,$data) = explode('@',$message);
        ConsulServices::getInstance()->updateServies($name,$data);
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
        $enableTcp = get_instance()->config->get('tcp.enable',false);
        $tcpPort = get_instance()->config->get('tcp.port',0);
        $enableHttp = get_instance()->config->get('http_server.enable',false);
        $httpPort = get_instance()->config->get('http_server.port',0);
        if (array_key_exists('services', $config)) {
            foreach ($config['services'] as $service) {
                if($enableHttp) {
                    $newConfig['services'][] = [
                        'id' => "Http_$service",
                        'name' => $service,
                        'address' => $config['bind_addr'],
                        'port' => $httpPort,
                        'tags' => ['http'],
                        'check' => [
                            'name' => 'status',
                            'http' => "http://localhost:$httpPort/$service/_consul_health",
                            'interval' => "10s",
                            'timeout' => "1s"
                        ]];
                }
                if($enableTcp){
                    $newConfig['services'][] = [
                        'id' => "Tcp_$service",
                        'name' => $service,
                        'address' => $config['bind_addr'],
                        'port' => $tcpPort,
                        'tags' => ['tcp'],
                        'check' => [
                            'name' => 'status',
                            'tcp' => "localhost:$tcpPort",
                            'interval' => "10s",
                            'timeout' => "1s"
                        ]];
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
        if (get_instance()->config->get('consul_enable', false)) {
            self::jsonFormatHandler();
            $consul_process = new \swoole_process(function ($process) {
                $process->name('SWD-CONSUL');
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
        if(get_instance()->server->worker_id==0) {
            if($is_leader!==self::$is_leader) {
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
        if(self::$is_leader==null){
            return false;
        }
        return self::$is_leader;
    }

    public static function getSessionID()
    {
        return self::$session_id;
    }
}