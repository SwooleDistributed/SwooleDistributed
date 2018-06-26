<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-12-18
 * Time: 下午2:57
 */

namespace Server\Components\CatCache;

use Server\Components\Event\EventDispatcher;
use Server\Components\Process\Process;

class CatCacheProcess extends Process
{
    const DB_LOG_HEADER = 'catdblog';
    const DB_HEADER = 'catdb';
    const READY = 'catcache_ready';
    /**
     * @var CatCacheHash
     */
    protected $map;
    /**
     * 配置
     * @var
     */
    protected $cache_config;
    /**
     * @var
     */
    protected $rpcProxyClass;
    /**
     * 自动保存的时间
     * @var
     */
    protected $auto_save_time;
    /**
     * 存盘的地址
     * @var
     */
    protected $save_dir;
    /**
     * @var
     */
    protected $save_file;
    /**
     * @var
     */
    protected $save_log_file;
    /**
     * @var
     */
    protected $save_temp_file;

    /**
     * @var
     */
    protected $delimiter;
    /**
     * @var
     */
    protected $read_buffer;

    protected $ready = false;
    /**
     * 锁
     * @var
     */
    protected $lock;

    public function __construct($name, $worker_id, $coroutine_need = true)
    {
        parent::__construct($name, $worker_id, $coroutine_need);
        $this->lock = new \swoole_lock(SWOOLE_MUTEX);
        $this->cache_config = $this->config->get('catCache');
        $this->delimiter = $this->cache_config['delimiter'] ?? ".";
        $this->map = new CatCacheHash($this, $this->delimiter);
        $this->auto_save_time = $this->cache_config['auto_save_time'];
        $this->save_dir = $this->cache_config['save_dir'];
        $this->save_file = $this->save_dir . "catCache.catdb";
        $this->save_log_file = $this->save_dir . "catCache.catdblog";
        $this->rpcProxyClass = $this->cache_config['rpcProxyClass'] ?? CatCacheRpcProxy::class;
        $this->setRPCProxy(new $this->rpcProxyClass);
    }

    /**
     * @param $process
     */
    public function start($process)
    {
        if (!file_exists($this->save_dir)) {
            mkdir($this->save_dir, 0777, true);
        }
        if (!file_exists($this->save_log_file)) {
            file_put_contents($this->save_log_file, "catdblog");
        }
        $this->readFromDb();
        swoole_timer_tick($this->auto_save_time * 1000, [$this, 'autoSave']);
        $this->rpcProxy->start();
    }

    /**
     * 清理Actor
     */
    public function clearActor()
    {
        unset($this->map["@Actor"]);
    }

    /**
     * 清理定时器
     */
    public function clearTimerBack()
    {
        unset($this->map["timer_back"]);
    }

    /**
     * 设置RPC代理
     * @param $object
     * @throws \ReflectionException
     */
    public function setRPCProxy($object)
    {
        parent::setRPCProxy($object);
        $object->setMap($this->map);
    }

    /**
     * @param $method
     * @param $params
     */
    public function writeLog($method, $params)
    {
        if (!$this->ready) {
            $this->lock->lock();
            $this->lock->unlock();
        }
        $one[0] = $method;
        $one[1] = $params;
        $buffer = \swoole_serialize::pack($one);
        $total_length = 4 + strlen($buffer);
        $data = pack('N', $total_length) . $buffer;
        swoole_async_write($this->save_log_file, $data);
    }

    /**
     * 自动保存
     */
    public function autoSave()
    {
        $this->save_temp_file = $this->save_dir . "catCache.catdb." . time();
        if (!file_exists($this->save_temp_file)) {
            file_put_contents($this->save_temp_file, self::DB_HEADER);
        }
        foreach ($this->map->getContainer() as $key => $value) {
            $one = [];
            $one[$key] = $value;
            $buffer = \swoole_serialize::pack($one);
            $total_length = 4 + strlen($buffer);
            $data = pack('N', $total_length) . $buffer;
            file_put_contents($this->save_temp_file, $data, FILE_APPEND);
        }
        //写完
        rename($this->save_temp_file, $this->save_file);
        if (file_exists($this->save_log_file)) {
            file_put_contents($this->save_log_file, self::DB_LOG_HEADER);
        }
    }

    /**
     * 读取
     */
    protected function readFromDb()
    {
        $this->lock->lock();
        if (is_file($this->save_file)) {
            $count = 0;
            swoole_async_read($this->save_file, function ($filename, $content) use (&$count) {
                $count++;
                if ($count == 1) {
                    $content = $this->checkFileHeader($content, self::DB_HEADER, self::DB_HEADER);
                }
                if (empty($content)) {
                    $this->read_buffer = '';
                    //读取结束
                    $this->readFromDbLog();
                    return false;
                }
                $this->read_buffer .= $content;
                $this->HELP_pack(function ($one) {
                    foreach ($one as $key => $value) {
                        $this->map->getContainer()[$key] = $value;
                    }
                });
                return true;
            });
        } else {
            $this->readFromDbLog();
        }

    }

    /**
     * 检查头
     * @param $content
     * @param $header
     * @param $name
     * @return bool|string
     * @throws \Exception
     */
    protected function checkFileHeader($content, $header, $name)
    {
        $len = strlen($header);
        $flag = substr($content, 0, $len);
        if ($flag != $header) {
            throw new \Exception("$name 文件已损坏");
        }
        return substr($content, $len);
    }

    /**
     * 从log读取
     */
    protected function readFromDbLog()
    {
        //看看有没有日志
        if (is_file($this->save_log_file)) {
            $count = 0;
            swoole_async_read($this->save_log_file, function ($filename, $content) use (&$count) {
                $count++;
                if ($count == 1) {
                    $content = $this->checkFileHeader($content, self::DB_LOG_HEADER, self::DB_LOG_HEADER);
                }
                if (empty($content)) {
                    $this->autoSave();
                    EventDispatcher::getInstance()->dispatch(self::READY, null, false, true);
                    secho("CatCache", "已完成加载缓存文件");
                    $this->lock->unlock();
                    $this->ready = true;
                    return false;
                }
                $this->read_buffer .= $content;
                $this->HELP_pack(function ($one) {
                    sd_call_user_func_array([$this->map, $one[0]], $one[1]);
                });
                return true;
            });
        }
    }

    /**
     * 是否就绪
     * @return mixed
     */
    public function isReady()
    {
        return $this->ready;
    }

    /**
     * 【帮助】解包
     */
    protected function HELP_pack($func)
    {
        while (strlen($this->read_buffer) > 0) {
            if (strlen($this->read_buffer) < 4) {
                break;
            }
            $head_len = unpack("N", $this->read_buffer)[1];
            if (strlen($this->read_buffer) >= $head_len)//有完整结果
            {
                $data = substr($this->read_buffer, 4, $head_len - 4);
                $this->read_buffer = substr($this->read_buffer, $head_len);
                $one = \swoole_serialize::unpack($data);
                $func($one);
            } else {
                break;
            }
        }
    }

    protected function onShutDown()
    {
        $this->autoSave();
        secho("CatCache", "缓存保存成功");
    }

}