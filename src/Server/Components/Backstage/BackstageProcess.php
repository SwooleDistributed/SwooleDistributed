<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-14
 * Time: 下午2:55
 */

namespace Server\Components\Backstage;

use Server\Components\Process\Process;
use Server\CoreBase\SwooleException;

class BackstageProcess extends Process
{
    /**
     * @param $process
     * @throws SwooleException
     */
    public function start($process)
    {
        $path = $this->config->get("backstage.bin_path", false);
        if ($path == false) {
            $path = BIN_DIR . "/exec/backstage";
        } else {
            $path = MYROOT . $path;
        }
        if (!is_file($path)) {
            secho("Backstage", "后台监控没有安装,如需要请联系白猫获取（需VIP客户）,或者将backstage.php配置中enable关闭");
            get_instance()->server->shutdown();
            exit();
        }
        $newPath = str_replace('backstage', getServerName() . "-backstage", $path);
        if (!is_file($newPath)) {
            copy($path, $newPath);
        }
        chmod($newPath, 0777);
        $this->exec($newPath, [$this->config->get("backstage.port"), $this->config->get("backstage.websocket_port")]);
    }

    protected function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }
}