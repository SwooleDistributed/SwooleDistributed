<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-14
 * Time: 下午2:55
 */

namespace Server\Components\SDHelp;

use Server\Components\Consul\ConsulLeader;
use Server\Components\Process\Process;
use Server\Components\Reload\InotifyReload;
use Server\Components\TimerTask\TimerTask;

class SDHelpProcess extends Process
{
    public $data = [];

    public function start($process)
    {
        parent::start($process);
        new TimerTask();
        if (get_instance()->config->get('consul.enable', false)) {
            new ConsulLeader();
        }
        if (get_instance()->config->get('auto_reload_enable', false)) {//代表启动单独进程进行reload管理
            new InotifyReload();
        }
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function getData($name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * @param $dataName
     * @param $data
     * @return bool
     */
    public function setData($dataName, $data)
    {
        $this->data[$dataName] = $data;
        return true;
    }

}