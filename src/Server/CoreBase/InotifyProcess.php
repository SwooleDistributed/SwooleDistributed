<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-19
 * Time: 上午9:17
 */

namespace Server\CoreBase;


class InotifyProcess
{
    const RELOAD_SIG = 'reload_sig';
    // 监控的目录，默认是src
    public $monitor_dir;
    public $inotifyFd;
    public $managePid;
    public $server;
    public function __construct($server)
    {
        $this->server = $server;
        $this->monitor_dir = realpath(__DIR__.'/../..');
        if(!extension_loaded('inotify'))
        {
            echo "FileMonitor : Please install inotify extension.\n";
            return;
        }

        global $monitor_files;
        // 初始化inotify句柄
        $this->inotifyFd = inotify_init();
        // 设置为非阻塞
        stream_set_blocking($this->inotifyFd, 0);
        // 递归遍历目录里面的文件
        $dir_iterator = new \RecursiveDirectoryIterator($this->monitor_dir);
        $iterator = new \RecursiveIteratorIterator($dir_iterator);
        foreach ($iterator as $file)
        {
            // 只监控php文件
            if(pathinfo($file, PATHINFO_EXTENSION) != 'php')
            {
                continue;
            }
            // 把文件加入inotify监控，这里只监控了IN_MODIFY文件更新事件
            $wd = inotify_add_watch($this->inotifyFd, $file, IN_MODIFY);
            $monitor_files[$wd] = $file;
        }
        // 监控inotify句柄可读事件
        swoole_event_add($this->inotifyFd,function ($inotify_fd){
            global $monitor_files;
            // 读取有哪些文件事件
            $events = inotify_read($inotify_fd);
            if($events)
            {
                // 检查哪些文件被更新了
                foreach($events as $ev)
                {
                    // 更新的文件
                    $file = $monitor_files[$ev['wd']];
                    echo "[RELOAD]  ".$file ." update\n";
                    unset($monitor_files[$ev['wd']]);
                    // 需要把文件重新加入监控
                    $wd = inotify_add_watch($inotify_fd, $file, IN_MODIFY);
                    $monitor_files[$wd] = $file;
                }
                //reload
                $this->server->reload();
            }
        },null,SWOOLE_EVENT_READ);
    }
}