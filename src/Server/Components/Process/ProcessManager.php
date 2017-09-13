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
     * @param string $name
     * @return Process
     * @throws \Exception
     */
    public function addProcess($class_name, $needCoroutine = true, $name = '')
    {
        $worker_id = get_instance()->worker_num + get_instance()->task_num + $this->atomic->get();
        $this->atomic->add();
        $names = explode("\\", $class_name);
        $process = new $class_name(getServerName() . "-" . $names[count($names) - 1], $worker_id, $needCoroutine);
        if (array_key_exists($class_name . $name, $this->map)) {
            throw new \Exception('存在相同类型的进程，需要设置别名');
        }
        $this->map[$class_name . $name] = $process;
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
     * @param bool $oneWay
     * @param string $name
     * @return mixed
     * @throws \Exception
     */
    public function getRpcCall($class_name, $oneWay = false, $name = '')
    {
        if (!array_key_exists($class_name . $name, $this->map)) {
            throw new \Exception("不存在$class_name 进程");
        }
        return Pool::getInstance()->get(RPCCall::class)->init($this->map[$class_name . $name], $oneWay);
    }

    /**
     * @param $class_name
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function getProcess($class_name, $name = '')
    {
        if (!array_key_exists($class_name . $name, $this->map)) {
            throw new \Exception("不存在$class_name 进程");
        }
        return $this->map[$class_name . $name];
    }
}