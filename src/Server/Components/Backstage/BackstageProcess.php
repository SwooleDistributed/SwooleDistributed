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
        if (!is_file(BIN_DIR . "/exec/backstage")) {
            secho("[Backstage]", "backstage没有安装,请下载最新的UI安装至bin/exec目录,或者在config/backstage.php中取消使能");
            get_instance()->server->shutdown();
            return;
        }

        $process->exec(BIN_DIR . "/exec/backstage", [$this->config->get("backstage.port")]);
    }

}