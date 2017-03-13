<?php
namespace Server\Components\Reload;
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-10
 * Time: 上午10:13
 */
class ReloadHelp
{
    /**
     * 开启进程
     */
    public static function startProcess()
    {
        if (get_instance()->config->get('auto_reload_enable', false)) {//代表启动单独进程进行reload管理
            $reload_process = new \swoole_process(function ($process) {
                $process->name('SWD-RELOAD');
                new InotifyProcess(get_instance()->server);
            }, false, 2);
            get_instance()->server->addProcess($reload_process);
        }
    }
}