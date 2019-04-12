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
    return getMillisecond() - \Server\Start::getStartMillisecond();
}

/**
 * 获取当前的时间(毫秒)
 * @return float
 */
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
        secho("STA", "[扩展依赖]缺少swoole扩展");
        $check = false;
    }
    if (extension_loaded('xhprof')) {
        secho("STA", "[扩展错误]不允许加载xhprof扩展，请去除");
        $check = false;
    }
    if (extension_loaded('xdebug')) {
        secho("STA", "[扩展错误]不允许加载xdebug扩展，请去除");
        $check = false;
    }
    if (version_compare(PHP_VERSION, '7.0.0', '<')) {
        secho("STA", "[版本错误]PHP版本必须大于7.0.0\n");
        $check = false;
    }
    if (version_compare(SWOOLE_VERSION, '4.0.3', '<')) {
        secho("STA", "[版本错误]Swoole版本必须大于4.0.3\n");
        $check = false;
    }
    if (!extension_loaded('redis')) {
        secho("STA", "[扩展依赖]缺少redis扩展");
        $check = false;
    }
    if (!extension_loaded('pdo')) {
        secho("STA", "[扩展依赖]缺少pdo扩展");
        $check = false;
    }

    if (get_instance()->config->has('consul_enable')) {
        secho("STA", "consul_enable配置已被弃用，请换成['consul']['enable']");
        $check = false;
    }
    if (get_instance()->config->has('use_dispatch')) {
        secho("STA", "use_dispatch配置已被弃用，请换成['dispatch']['enable']");
        $check = false;
    }
    if (get_instance()->config->has('dispatch_heart_time')) {
        secho("STA", "dispatch_heart_time配置已被弃用，请换成['dispatch']['heart_time']");
        $check = false;
    }
    if (get_instance()->config->get('config_version', '') != \Server\SwooleServer::config_version) {
        secho("STA", "配置文件有不兼容的可能，请将vendor/tmtbe/swooledistributed/src/config目录替换src/config目录，然后重新配置");
        $check = false;
    }
    return $check;
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

function displayExceptionHandler(\Throwable $exception)
{
    get_instance()->log->error($exception->getMessage(), ["trace" => $exception->getTrace()]);
    secho("EX", "------------------发生异常：" . $exception->getMessage() . "-----------------------");
    $string = $exception->getTraceAsString();
    $arr = explode("#", $string);
    unset($arr[0]);
    foreach ($arr as $value) {
        secho("EX", "#" . $value);
    }
}

/**
 * 代替sleep
 * @param $ms
 * @return mixed
 */
function sleepCoroutine($ms)
{
    \co::sleep($ms / 1000);
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
    global $node_name;
    if (!empty($node_name)) {
        return $node_name;
    }
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
            secho("STA", "$dir 目录不存在\n");
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
    secho("EX", "运行链路:");
    foreach ($context['RunStack'] as $key => $value) {
        secho("EX", "$key# $value");
    }
}

function secho($tile, $message)
{
    ob_start();
    if (is_string($message)) {
        $message = ltrim($message);
        $message = str_replace(PHP_EOL, '', $message);
    }
    print_r($message);
    $content = ob_get_contents();
    ob_end_clean();
    $could = false;
    if (empty(\Server\Start::getDebugFilter())) {
        $could = true;
    } else {
        foreach (\Server\Start::getDebugFilter() as $filter) {
            if (strpos($tile, $filter) !== false || strpos($content, $filter) !== false) {
                $could = true;
                break;
            }
        }
    }

    $content = explode("\n", $content);
    $send = "";
    foreach ($content as $value) {
        if (!empty($value)) {
            $echo = "[$tile] $value";
            $send = $send . $echo . "\n";
            if ($could) {
                echo " > $echo\n";
            }
        }
    }
    try {
        if (get_instance() != null) {
            get_instance()->pub('$SYS/' . getNodeName() . "/echo", $send);
        }
    } catch (Exception $e) {

    }
}

function setTimezone()
{
    date_default_timezone_set('Asia/Shanghai');
}

function format_date($time)
{
    $day = (int)($time / 60 / 60 / 24);
    $hour = (int)($time / 60 / 60) - 24 * $day;
    $mi = (int)($time / 60) - 60 * $hour - 60 * 24 * $day;
    $se = $time - 60 * $mi - 60 * 60 * $hour - 60 * 60 * 24 * $day;
    return "$day 天 $hour 小时 $mi 分 $se 秒";
}

function sd_call_user_func($function, ...$parameter)
{
    if (is_callable($function)) {
        return $function(...$parameter);
    }
}

function sd_call_user_func_array($function, $parameter)
{
    if (is_callable($function)) {
        return $function(...$parameter);
    }
}

/**
 * @param $arr
 * @throws \Server\Asyn\MQTT\Exception
 */
function sd_debug($arr)
{
    Server\Components\SDDebug\SDDebug::debug($arr);
}

function read_dir_queue($dir)
{
    $files = array();
    $queue = array($dir);
    while ($data = each($queue)) {
        $path = $data['value'];
        if (is_dir($path) && $handle = opendir($path)) {
            while ($file = readdir($handle)) {
                if ($file == '.' || $file == '..') continue;
                $files[] = $real_path = realpath($path . '/' . $file);
                if (is_dir($real_path)) $queue[] = $real_path;
            }
        }
        closedir($handle);
    }
    $result = [];
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) == "php") {
            $result[] = $file;
        }
    }
    return $result;
}

if (!function_exists("swoole_async_read")) {
    function swoole_async_read($file_path, $callback, $size = 8192, $offset = 0)
    {
        go(function () use ($file_path, $callback, $size, $offset) {
            $fp = fopen($file_path, "r");
            while (!feof($fp)) {//循环读取，直至读取完整个文件
                $data = fread($fp, $size);
                $callback($file_path, $data);
            }
            $callback($file_path, '');
            fclose($fp);
        });
    }
}
if (!function_exists("swoole_async_write")) {
    function swoole_async_write($file_path, $data)
    {
        go(function () use ($file_path, $data) {
            file_put_contents($file_path, $data, FILE_APPEND);
        });
    }
}

if (!function_exists("swoole_async_dns_lookup")) {
    function swoole_async_dns_lookup($host, $callback)
    {
        if (get_instance()->isTaskWorker()) return;
        go(function () use ($host, $callback) {
            $ip = Swoole\Coroutine::gethostbyname($host);
            $callback($host, $ip);
        });
    }
}

if (!class_exists("swoole_client")) {
    class swoole_client
    {
        private $client;
        private $map = [];

        function __construct($ip, $port)
        {
            if (get_instance()->isTaskWorker()) return;
            $this->client = new Swoole\Coroutine\Client($ip, $port);
        }

        function set($data)
        {
            $this->client->set($data);
        }

        public function on($name, $callback)
        {
            $this->map[$name] = $callback;
        }

        public function connect($host, $port)
        {
            go(function () use ($host, $port) {
                if (!$this->client->connect($host, $port, 0.5)) {
                    $this->map["error"]($this);
                } else {
                    $this->map['connect']($this);
                    while (true) {
                        $data = $this->client->recv();
                        if ($data == false) {
                            $this->map['close']($this);
                            break;
                        } else {
                            $this->map['receive']($this, $data);
                        }
                    }
                }
            });
        }

        public function close()
        {
            return $this->client->close();
        }

        public function __get($name)
        {
            return $this->client->$name;
        }
    }
}
if (!class_exists("swoole_http_client")) {
    class swoole_http_client
    {
        private $client;
        private $map = [];

        function __construct($ip, $port, $ssl)
        {
            if (get_instance()->isTaskWorker()) return;
            $this->client = new Swoole\Coroutine\Http\Client($ip, $port, $ssl);
        }

        function set($data)
        {
            $this->client->set($data);
        }

        function setMethod($method)
        {
            $this->client->setMethod($method);
        }

        function setHeaders($headers)
        {
            $this->client->setHeaders($headers);
        }

        function setCookies($cookies)
        {
            $this->client->setCookies($cookies);
        }

        function setData($data)
        {
            $this->client->setData($data);
        }

        function addFile(...$file)
        {
            $this->client->addFile(...$file);
        }

        function execute($path, $callback)
        {
            go(function () use ($path, $callback) {
                $this->client->execute($path);
                $callback($this);
            });
        }

        function download($path, $filename, $callback, $offset)
        {
            go(function () use ($path, $filename, $callback, $offset) {
                $this->client->download($path, $filename, $offset);
                $callback($this);
            });
        }

        public function __get($name)
        {
            return $this->client->$name;
        }

        public function on($name, $callback)
        {
            $this->map[$name] = $callback;
        }
    }
}
