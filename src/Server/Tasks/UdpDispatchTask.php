<?php
namespace Server\Tasks;

use Server\CoreBase\Task;

/**
 * dispatch 的udp广播，用于通知dispatch
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午1:06
 */
class UdpDispatchTask extends Task
{
    public function send()
    {
        if ($this->config['use_dispatch']??false) {
            $servers = $this->config->get('dispatch_server.dispatch_servers',[]);
            if(count($servers)!=0){
                foreach ($servers as $ip) {
                    $this->server->sendto($ip,$this->config['dispatch_server']['port'],$this->config['dispatch_server']['password']);
                }
            }else {
                //广播
                $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
                socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
                socket_connect($sock, "255.255.255.255", $this->config['dispatch_server']['port']);
                $buf = $this->config['dispatch_server']['password'];
                socket_write($sock, $buf, strlen($buf));
                socket_close($sock);
            }
        }
    }
}