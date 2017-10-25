<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 上午11:38
 */
/**
 * 获取实例
 * @return \Server\SwooleDistributedServer
 */
function &get_instance()
{
    return \Server\SwooleDistributedServer::get_instance();
}

/**
 * 获取服务器运行到现在的毫秒数
 * @return int
 */
function getTickTime()
{
    return \Server\SwooleDistributedServer::get_instance()->tickTime;
}

function getMillisecond()
{
    list($t1, $t2) = explode(' ', microtime());
    return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
}

function shell_read()
{
    $fp = fopen('php://stdin', 'r');
    $input = fgets($fp, 255);
    fclose($fp);
    $input = chop($input);
    return $input;
}

/**
 * http发送文件
 * @param $path
 * @param $response
 * @return mixed
 */
function httpEndFile($path, $request, $response)
{
    $path = urldecode($path);
    if (!file_exists($path)) {
        return false;
    }
    $lastModified = gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT';
    //缓存
    if (isset($request->header['if-modified-since']) && $request->header['if-modified-since'] == $lastModified) {
        $response->status(304);
        $response->end('');
        return true;
    }
    $extension = get_extension($path);
    $normalHeaders = get_instance()->config->get("fileHeader.normal", ['Content-Type: application/octet-stream']);
    $headers = get_instance()->config->get("fileHeader.$extension", $normalHeaders);
    foreach ($headers as $value) {
        list($hk, $hv) = explode(': ', $value);
        $response->header($hk, $hv);
    }
    $response->header('Last-Modified', $lastModified);
    $response->sendfile($path);
    return true;
}

/**
 * 获取后缀名
 * @param $file
 * @return mixed
 */
function get_extension($file)
{
    $info = pathinfo($file);
    return strtolower($info['extension'] ?? '');
}

/**
 * php在指定目录中查找指定扩展名的文件
 * @param $path
 * @param $ext
 * @return array
 */
function get_files_by_ext($path, $ext)
{
    $files = array();
    if (is_dir($path)) {
        $handle = opendir($path);
        while ($file = readdir($handle)) {
            if ($file[0] == '.') {
                continue;
            }
            if (is_file($path . $file) and preg_match('/\.' . $ext . '$/', $file)) {
                $files[] = $file;
            }
        }
        closedir($handle);
    }
    return $files;
}

function getLuaSha1($name)
{
    return \Server\Asyn\Redis\RedisLuaManager::getLuaSha1($name);
}

/**
 * 检查扩展
 * @return bool
 */
function checkExtension()
{
    $check = true;
    if (!extension_loaded('swoole')) {
        print_r("[扩展依赖]缺少swoole扩展\n");
        $check = false;
    }
    if (version_compare(PHP_VERSION, '7.0.0', '<')) {
        print_r("[版本错误]PHP版本必须大于7.0.0\n");
        $check = false;
    }
    if (version_compare(SWOOLE_VERSION, '1.9.18', '<')) {
        print_r("[版本建议]Swoole推荐使用1.9.18版本,之前版本存在bug\n");
        $check = false;
    }

    if (SWOOLE_VERSION[0] == 2) {
        print_r("[版本错误]不支持2.0版本swoole，请安装1.9版本\n");
        $check = false;
    }
    if (!class_exists('swoole_redis')) {
        print_r("[编译错误]swoole编译缺少--enable-async-redis,具体参见文档http://docs.sder.xin/%E7%8E%AF%E5%A2%83%E8%A6%81%E6%B1%82.html");
        $check = false;
    }
    if (!extension_loaded('redis')) {
        print_r("[扩展依赖]缺少redis扩展\n");
        $check = false;
    }
    if (!extension_loaded('pdo')) {
        print_r("[扩展依赖]缺少pdo扩展\n");
        $check = false;
    }

    if (get_instance()->config->has('consul_enable')) {
        print_r("consul_enable配置已被弃用，请换成['consul']['enable']\n");
        $check = false;
    }
    if (get_instance()->config->has('use_dispatch')) {
        print_r("use_dispatch配置已被弃用，请换成['dispatch']['enable']\n");
        $check = false;
    }
    if (get_instance()->config->has('dispatch_heart_time')) {
        print_r("dispatch_heart_time配置已被弃用，请换成['dispatch']['heart_time']\n");
        $check = false;
    }
    if (get_instance()->config->get('config_version', '') != \Server\SwooleServer::config_version) {
        print_r("配置文件有不兼容的可能，请将vendor/tmtbe/swooledistributed/src/config目录替换src/config目录，然后重新配置\n");
        $check = false;
    }

    $dispatch_enable = get_instance()->config->get('dispatch.enable', false);
    if ($dispatch_enable) {
        if (!get_instance()->config->get('redis.enable', true)) {
            print_r("开启dispatch，就必须启动redis的配置\n");
            $check = false;
        }
    }
    return $check;
}

/**
 * 断点调试
 */
function breakpoint()
{
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    print_r($backtrace);
    print_r("断点中任意键继续:");
    shell_read();
}

/**
 * 是否是mac系统
 * @return bool
 */
function isDarwin()
{
    if (PHP_OS == "Darwin") {
        return true;
    } else {
        return false;
    }
}

/**
 * 代替sleep
 * @param $time
 * @return mixed
 */
function sleepCoroutine($time)
{
    return \Server\Memory\Pool::getInstance()->get(\Server\CoreBase\SleepCoroutine::class)->setTimeout($time);
}

/**
 * @param string $dev
 * @return string
 */
function getServerIp($dev = 'eth0')
{
    return exec("ip -4 addr show $dev | grep inet | awk '{print $2}' | cut -d / -f 1");
}

/**
 * @return string
 */
function getBindIp()
{
    return get_instance()->getBindIp();
}

/**
 * @return array|false|mixed|string
 */
function getNodeName()
{
    $env_SD_NODE_NAME = getenv("SD_NODE_NAME");
    if (!empty($env_SD_NODE_NAME)) {
        $node_name = $env_SD_NODE_NAME;
    } else {
        if (!isset(get_instance()->config['consul']['node_name'])
            || empty(get_instance()->config['consul']['node_name'])) {
            $node_name = exec('hostname');
        } else {
            $node_name = get_instance()->config['consul']['node_name'];
        }
    }
    return $node_name;
}

/**
 * @return mixed|string
 */
function getServerName()
{
    return get_instance()->config['name'] ?? 'SWD';
}

/**
 * @return string
 */
function getConfigDir()
{
    $env_SD_CONFIG_DIR = getenv("SD_CONFIG_DIR");
    if (!empty($env_SD_CONFIG_DIR)) {
        $dir = CONFIG_DIR . '/' . $env_SD_CONFIG_DIR;
        if (!is_dir($dir)) {
            print_r("$dir 目录不存在\n");
            exit();
        }
        return $dir;
    } else {
        return CONFIG_DIR;
    }
}

/**
 * @param string $prefix
 * @return string
 */
function create_uuid($prefix = "")
{    //可以指定前缀
    $str = md5(uniqid(mt_rand(), true));
    $uuid = substr($str, 0, 8) . '-';
    $uuid .= substr($str, 8, 4) . '-';
    $uuid .= substr($str, 12, 4) . '-';
    $uuid .= substr($str, 16, 4) . '-';
    $uuid .= substr($str, 20, 12);
    return $prefix . $uuid;
}

function print_context($context)
{
    echo "--------------------------------------------------------------------------\n";
    echo "运行堆栈:\n";
    foreach ($context['RunStack'] as $key => $value) {
        echo "$key# $value\n";
    }
    echo "--------------------------------------------------------------------------\n";
}