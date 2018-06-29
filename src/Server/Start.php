<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-7-25
 * Time: 上午10:29
 */

namespace Server;


use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

class Start
{
    /**
     * Daemonize.
     *
     * @var bool
     */
    protected static $daemonize = false;

    /**
     * @var array
     */
    protected static $debug_filter;

    /**
     * @var
     */
    protected static $debug;
    /**
     * @var
     */
    protected static $xdebug;
    /**
     * @var
     */
    protected static $coverage;
    /**
     * @var string
     */
    protected static $startTime;

    /**
     * @var
     */
    protected static $startMillisecond;

    /**
     * @var
     */
    protected static $leader;

    /**
     * @var
     */
    protected static $xdebug_table;
    /**
     * 单元测试
     * @var bool
     */
    public static $testUnity = false;
    /**
     * 单元测试文件目录
     * @var string
     */
    public static $testUnityDir = '';

    /**
     * @var SymfonyStyle
     */
    public static $io;

    /**
     * Run all worker instances.
     *
     * @return void
     * @throws \Exception
     */
    public static function run()
    {
        self::$debug = new \swoole_atomic(0);
        self::$xdebug = false;
        self::$coverage = false;
        self::$leader = new \swoole_atomic(0);
        self::$xdebug_table = new \swoole_table(1);
        self::$xdebug_table->column('wid', \swoole_table::TYPE_INT, 8);
        self::$xdebug_table->column('cid', \swoole_table::TYPE_INT, 8);
        self::$xdebug_table->create();
        self::$startTime = date('Y-m-d H:i:s');
        self::$startMillisecond = getMillisecond();
        self::setProcessTitle(getServerName());
        $application = new Application();
        $input = new ArgvInput();
        $output = new ConsoleOutput();
        self::$io = new SymfonyStyle($input, $output);
        self::addDirCommand(SERVER_DIR, "Server", $application);
        self::addDirCommand(APP_DIR, "app", $application);
        $application->run($input, $output);
    }

    /**
     * @param $root
     * @param $namespace
     * @param $application
     */
    private static function addDirCommand($root, $namespace, $application)
    {
        $path = $root . "/Console";
        if (!file_exists($path)) {
            return;
        }
        $file = scandir($path);
        foreach ($file as $value) {
            list($name, $ex) = explode('.', $value);
            if (!empty($name) && $ex == 'php') {
                $class = "$namespace\\Console\\$name";
                if (class_exists($class)) {
                    $instance = new $class($name);
                    if ($instance instanceof Command) {
                        $application->add($instance);
                    }
                }
            }
        }
    }

    /**
     * Set process name.
     *
     * @param string $title
     * @return void
     */
    public static function setProcessTitle($title)
    {
        if (isDarwin()) {
            return;
        }
        // >=php 5.5
        if (function_exists('cli_set_process_title')) {
            @cli_set_process_title($title);
        } // Need proctitle when php<=5.5 .
        else {
            @swoole_set_process_name($title);
        }
    }

    public static function setDaemonize()
    {
        self::$daemonize = true;
    }

    public static function getDaemonize()
    {
        return self::$daemonize ? 1 : 0;
    }

    public static function getDebug()
    {
        return self::$debug->get() == 1 ? true : false;
    }

    public static function setDebug($debug)
    {
        self::$debug->set($debug ? 1 : 0);
        if ($debug) {
            secho("SYS", "DEBUG开启");
        } else {
            secho("SYS", "DEBUG关闭");
        }
    }
    public static function getCoverage()
    {
        return self::$coverage;
    }

    public static function setCoverage($coverage)
    {
        self::$coverage = $coverage;
    }
    public static function getXDebug()
    {
        return self::$xdebug;
    }

    public static function setXDebug($debug)
    {
        self::$xdebug = $debug;
    }
    public static function isLeader()
    {
        return self::$leader->get() == 1 ? true : false;
    }

    public static function setLeader($bool)
    {
        self::$leader->set($bool ? 1 : 0);
        if (get_instance()->isCluster()) {
            if ($bool) {
                secho("CONSUL", "Leader变更，被选举为Leader");
            } else {
                secho("CONSUL", "Leader变更，本机不是Leader");
            }
        }
    }

    public static function getDebugFilter()
    {
        return self::$debug_filter ?? [];
    }

    public static function getStartTime()
    {
        return self::$startTime;
    }

    public static function getStartMillisecond()
    {
        return self::$startMillisecond;
    }

    public static function getLockXDebug()
    {
        $result = self::$xdebug_table->get('debug');
        $wid = get_instance()->getWorkerId();
        $cid = \Swoole\Coroutine::getuid();
        if($cid==-1){
            return false;
        }
        if($result===false){
            self::$xdebug_table->set("debug",['wid'=>$wid,'cid'=>$cid]);
            return true;
        }else{
            if($result['wid']==$wid&&$result['cid']==$cid){
                return true;
            }else{
                return false;
            }
        }
    }
    public static function cleanXDebugLock()
    {
        self::$xdebug_table->del('debug');
    }
}