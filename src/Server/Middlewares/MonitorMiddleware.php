<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-9-28
 * Time: 下午5:25
 */

namespace Server\Middlewares;


use Server\Components\Middleware\Middleware;
use Server\Components\Process\ProcessManager;
use Server\Components\SDHelp\SDHelpProcess;

class MonitorMiddleware extends Middleware
{
    protected $start_run_time;
    protected static $efficiency_monitor_enable;

    public function __construct()
    {
        parent::__construct();
        if (MonitorMiddleware::$efficiency_monitor_enable == null) {
            MonitorMiddleware::$efficiency_monitor_enable = $this->config['log'][$this->config['log']['active']]['efficiency_monitor_enable'];
        }
    }

    public function before_handle()
    {
        $this->start_run_time = microtime(true);
        $this->context['start_time'] = date('Y-m-d H:i:s');
    }

    public function after_handle($path)
    {
        $this->context['path'] = $path;
        $this->context['execution_time'] = (microtime(true) - $this->start_run_time) * 1000;
        ProcessManager::getInstance()->getRpcCall(SDHelpProcess::class, true)->addStatistics($path, $this->context['execution_time']);
        if (self::$efficiency_monitor_enable) {
            $this->log('Monitor');
        }
    }
}