<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-15
 * Time: 上午9:41
 */

namespace Server\Components\Process;

use Server\Memory\Pool;

/**
 * 进程管理
 * Class ProcessManager
 * @package Server\Components\Process
 */
class ProcessManager
{
    /**
     * @var ProcessManager
     */
    protected static $instance;
    protected $atomic;
    protected $map = [];

    /**
     * ProcessManager constructor.
     */
    public function __construct()
    {
        $this->atomic = new \swoole_atomic();
    }

    /**
     * @param $class_name
     * @param bool $needCoroutine
     * @return Process
     */
    public function addProcess($class_name, $needCoroutine = true)
    {
        $worker_id = get_instance()->worker_num + get_instance()->task_num + $this->atomic->get();
        $this->atomic->add();
        $names = explode("\\", $class_name);
        $process = new $class_name('SWD-' . $names[count($names) - 1], $worker_id, $needCoroutine);
        $this->map[$class_name] = $process;
        return $process;
    }

    /**
     * @return ProcessManager
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new ProcessManager();
        }
        return self::$instance;
    }

    /**
     * @param $class_name
     * @param $oneWay
     * @return mixed
     * @throws \Exception
     */
    public function getRpcCall($class_name, $oneWay = false)
    {
        if (!array_key_exists($class_name, $this->map)) {
            throw new \Exception("不存在$class_name 进程");
        }
        return Pool::getInstance()->get(RPCCall::class)->init($this->map[$class_name], $oneWay);
    }

    /**
     * @param $class_name
     * @return mixed
     * @throws \Exception
     */
    public function getProcess($class_name)
    {
        if (!array_key_exists($class_name, $this->map)) {
            throw new \Exception("不存在$class_name 进程");
        }
        return $this->map[$class_name];
    }
}