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
     */
    public function start($process)
    {
        $path = $this->config->get("backstage.bin_path", false);
        if ($path == false) {
            $path = BIN_DIR . "/exec/backstage";
        } else {
            $path = MYROOT . $path;
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