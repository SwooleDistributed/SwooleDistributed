<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-7-27
 * Time: 上午9:48
 */

namespace Server\Memory;


use Server\CoreBase\SwooleException;
use Server\Coroutine\CoroutineNull;

/**
 * 跨进程的内存缓存系统
 * Class Cache
 * @package Server\Memory
 */
class Cache
{
    protected static $caches = [];
    protected $taskName;
    protected $db;
    protected $task;

    /**
     * Cache constructor.
     * @param $taskName
     * @param int $db 0-(taskNum-1)
     * @throws SwooleException
     */
    public function __construct($taskName, $db = 0)
    {
        $this->taskName = $taskName;
        $this->db = $db;
        if ($db >= get_instance()->task_num) {
            throw new SwooleException('db的范围为0->(taskNum-1)');
        }
        if (array_key_exists($taskName, self::$caches)) {
            throw new SwooleException("$taskName cache名已存在，请通过getCache方法创建");
        }
        $this->task = get_instance()->loader->task($this->taskName);
        self::$caches[$taskName] = $this;
    }

    public function __call($name, $arguments)
    {
        $this->task->__call($name, $arguments);
        $result = $this->task->startTaskWait(0.5, $this->db);
        if ($result instanceof CoroutineNull) {
            $result = null;
        }
        return $result;
    }

    public function __destruct()
    {
        $this->task->destroy();
    }

    /**
     * @param $taskName
     * @param int $db
     * @return mixed|null|Cache
     */
    public static function getCache($taskName, $db = 0)
    {
        $cache = self::$caches[$taskName] ?? null;
        if ($cache == null) {
            return new Cache($taskName, $db);
        } else {
            return $cache;
        }
    }
}