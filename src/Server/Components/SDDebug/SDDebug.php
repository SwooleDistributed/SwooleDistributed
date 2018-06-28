<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/26
 * Time: 14:57
 */

namespace Server\Components\SDDebug;


use Server\Components\Event\EventCoroutine;
use Server\Components\Event\EventDispatcher;
use Server\Components\Process\ProcessManager;
use Server\Components\SDHelp\SDHelpProcess;
use Server\Start;
use Server\SwooleServer;

class SDDebug
{
    const EVENT_XDEBUG_TAG = "@SDXDEBUG-BREAK";
    const XDEBUG_FILES = "XDEBUG_FILES";

    /**
     * @param $fileDebugs
     * @throws \Exception
     */
    public static function debugFile($fileDebugs)
    {
        self::stopDebug(false);
        $saveDebugFiles = [];
        foreach ($fileDebugs as $fileDebug) {
            $saveDebugFiles[] = $fileDebug->file;
        }
        ProcessManager::getInstance()->getRpcCall(SDHelpProcess::class, true)->setData(self::XDEBUG_FILES, $saveDebugFiles);
        foreach ($fileDebugs as $fileDebug) {
            $file = APP_DIR . $fileDebug->file;
            $lines = $fileDebug->lines;
            $str = file_get_contents($file);
            $line_srcs = explode("\n", $str);
            foreach ($lines as $line) {
                $line_srcs[$line-1] = "/**DEBUG*/ sd_debug(get_defined_vars());". $line_srcs[$line-1];
            }
            $new_src = implode("\n", $line_srcs);
            $newfile = str_replace("src/app", "src/app-debug", $file);
            $dir = pathinfo($newfile, PATHINFO_DIRNAME);
            is_dir($dir) OR mkdir($dir, 0777, true);
            file_put_contents($newfile, $new_src);
        }
        get_instance()->server->reload();
    }

    /**
     * @throws \Exception
     */
    public static function nextBreak()
    {
        EventDispatcher::getInstance()->dispatch(self::EVENT_XDEBUG_TAG);
    }

    /**
     * @param $arr
     * @throws \Server\Asyn\MQTT\Exception
     */
    public static function debug($arr)
    {
        Start::cleanXDebugLock();
        //只锁住一个协程
        if (Start::getLockXDebug()) {
            $backdata = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT);
            $defined_vars = [];
            SDDebug::traceObject($arr, $defined_vars);
            $defined_vars['text']="临时变量";
            $args = [];
            $args['text']="函数参数";
            SDDebug::traceObject($backdata[2]['args'], $args);
            $nowthis = [];
            $nowthis['text']='$this('.$backdata[2]['class'].')';
            SDDebug::traceObject($backdata[2]['object'], $nowthis);
            $data['vars'] = [$args,$defined_vars,$nowthis];
            $data['file'] = $backdata[1]["file"];
            $data['file'] = explode("/app-debug",$data['file'])[1];
            $data['line'] = $backdata[1]["line"];
            get_instance()->pub('$SYS_XDEBUG/BreakPoint', $data);
            EventDispatcher::getInstance()->addOnceCoroutine(self::EVENT_XDEBUG_TAG, function (EventCoroutine $eventCoroutine) {
                $eventCoroutine->setTimeout(9999999);
            });
        }
    }

    public static function traceObject($arr, &$result, $deep = 0)
    {
        $deep++;
        foreach ($arr as $key => $value) {
            if (is_array($value)) {
                $one['text'] = $key . "(Array)";
                $result['children'][] = $one;
                $index = count($result['children'])-1;
                if ($deep < 5) {
                    self::traceObject($value, $result['children'][$index], $deep);
                }
            } elseif (is_object($value)) {
                if(is_resource($value)){
                    return;
                }
                if(is_callable($value)){
                    return;
                }
                if ($value instanceof SwooleServer) {
                    return;
                }
                $one['text'] = $key . "(" . get_class($value) . ")";
                $result['children'][] = $one;
                $index = count($result['children'])-1;
                if ($deep < 5) {
                    self::traceObject(get_object_vars($value), $result['children'][$index], $deep);
                }
            } elseif (is_string($value) || is_numeric($value)) {
                $one['text'] = $key . " = $value";
                $result['children'][] = $one;
            }
        }
    }

    /**
     * @param bool $reload
     * @throws \Exception
     */
    public static function stopDebug($reload = true)
    {
        $saveDebugFiles = ProcessManager::getInstance()->getRpcCall(SDHelpProcess::class)->getData(self::XDEBUG_FILES);
        if (empty($saveDebugFiles)) return;
        foreach ($saveDebugFiles as $file) {
            $file = APP_DIR . $file;
            $str = file_get_contents($file);
            $newfile = str_replace("src/app", "src/app-debug", $file);
            $dir = pathinfo($newfile, PATHINFO_DIRNAME);
            is_dir($dir) OR mkdir($dir, 0777, true);
            file_put_contents($newfile, $str);
        }
        if ($reload) {
            get_instance()->server->reload();
        }
    }
}