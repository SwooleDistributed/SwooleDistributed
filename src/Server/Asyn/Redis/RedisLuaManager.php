<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-29
 * Time: 下午1:47
 */

namespace Server\Asyn\Redis;

/**
 * Redis lua 脚本管理器
 * Class RedisLuaManager
 * @package Server\Asyn\Redis
 */
class RedisLuaManager
{
    /**
     * @var array
     */
    public static $registerMap;

    /**
     * @var \Redis
     */
    public $redis;

    public function __construct($redis)
    {
        $this->redis = $redis;
    }

    /**
     * 将文件夹内所有lua脚本注册
     * @param $file
     */
    public function registerFile($file)
    {
        $file = realpath($file) . DIRECTORY_SEPARATOR;
        $files = get_files_by_ext($file, 'lua');
        $sha1s = [];
        $luas = [];
        $names = [];
        foreach ($files as $file_name) {
            $lua = file_get_contents($file . $file_name);//将整个文件内容读入到一个字符串中
            $lua = str_replace("\r\n", " ", $lua);
            $lua = str_replace("\n", " ", $lua);
            list($name, $ext) = explode('.', $file_name);
            $sha1s[] = sha1($lua);
            $luas[] = $lua;
            $names[] = $name;
        }
        $this->registerLuas($sha1s, $luas, $names);
    }

    /**
     * @param $sha1s
     * @param $luas
     * @param $names
     */
    public function registerLuas($sha1s, $luas, $names)
    {
        $exists = $this->redis->script("exists", ...$sha1s);
        $count = count($exists);
        for ($i = 0; $i < $count; $i++) {
            if (!$exists[$i]) {
                $this->redis->script("load", $luas[$i]);
            }
            self::$registerMap[$names[$i]] = $sha1s[$i];
            print_r("加载RedisLua脚本=>$names[$i]\n");
        }

    }

    /**
     * @param $name
     * @return bool|mixed
     */
    public static function getLuaSha1($name)
    {
        return self::$registerMap[$name]??false;
    }

}