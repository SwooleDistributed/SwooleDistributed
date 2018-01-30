<?php
/**
 * Created by PhpStorm.
 * User: Xavier
 * Date: 18-1-25
 * Time: 下午2:26
 */

namespace Server\Components\Consul;

use Server\Components\Process\Process;
use Server\CoreBase\PortManager;
use Server\CoreBase\SwooleException;

class FabioProcess extends Process
{
    /**
     * @param $process
     * @throws SwooleException
     */
    public function start($process)
    {
        if (!is_file(BIN_DIR . "/exec/fabio")) {
            secho("[CONSUL]", "fabio没有安装,请下载最新的fabio安装至bin/exec目录,或者在config/consul.php中取消使能");
            get_instance()->server->shutdown();
            exit();
        }
        $fabio = get_instance()->config['fabio'];
        $http_port=isset($fabio['http_port'])?$fabio['http_port']:6666;
        // $tcp_port=isset($fabio['tcp_port'])?$fabio['tcp_port']:1234;
        $this->exec(BIN_DIR . "/exec/fabio", ['-proxy.addr',":$http_port;proto=http"]);
    }
    protected function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }
}