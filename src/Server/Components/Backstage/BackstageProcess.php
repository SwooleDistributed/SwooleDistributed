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
            secho("[Backstage]", "后台监控没有安装,如需要请联系白猫获取（需VIP客户）");
            get_instance()->server->shutdown();
            return;
        }

        $process->exec(BIN_DIR . "/exec/backstage", [$this->config->get("backstage.port")]);
    }

}