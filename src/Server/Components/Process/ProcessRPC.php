<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-11-15
 * Time: 下午1:50
 */

namespace Server\Components\Process;


use Server\CoreBase\Child;
use Server\SwooleMarco;
use Server\Test\DocParser;

abstract class ProcessRPC extends Child
{
    /**
     * 协程支持
     * @var bool
     */
    protected $coroutine_need = true;
    protected $token = 0;
    protected $rpcProxy;

    /**
     * 設置RPC代理
     * @param $object
     */
    public function setRPCProxy($object)
    {
        $this->rpcProxy = $object;
        $this->phaseProxy($object);
    }

    /**
     * @param $object
     */
    public function phaseProxy($object)
    {
        $reflection = new \ReflectionClass (get_class($object));
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        //遍历所有的方法
        foreach ($methods as $method) {
            //获取方法的注释
            $doc = $method->getDocComment();
            //解析注释
            $info = DocParser::getInstance()->parse($doc);
            $method_name = $method->getName();
            if (isset($info['oneWay'])) {
                ProcessManager::getInstance()->oneWayFucName[$method_name] = $method_name;
            }
        }
    }

    /**
     * 是否单向
     * @param $func
     * @return bool
     */
    public function isOneWay($func)
    {
        return array_key_exists($func, ProcessManager::getInstance()->oneWayFucName);
    }

    /**
     * @param $name
     * @param $arguments
     * @param $oneWay
     * @param $worker_id
     * @return string
     */
    public function processRpcCall($name, $arguments, $oneWay, $worker_id)
    {
        $this->token++;
        $my_worker_id = get_instance()->getWorkerId();
        $message['worker_id'] = $my_worker_id;
        $message['arg'] = $arguments;
        $message['func'] = $name;
        $message['token'] = "[PR]$my_worker_id->$worker_id:" . $this->token;
        $message['oneWay'] = $oneWay;
        if ($my_worker_id == $worker_id) {
            \swoole_event_defer(function () use (&$message) {
                $this->processPpcRun($message);
            });
            return $message['token'];
        }
        $this->sendMessage(get_instance()->packServerMessageBody(SwooleMarco::PROCESS_RPC, $message), $worker_id);
        return $message['token'];
    }

    /**
     * 跨进程调用public方法
     * @param $message
     * @throws \Exception
     */
    protected function processPpcRun($message)
    {
        go(function () use ($message) {
            $func = $message['func'];
            $context = $this;
            if ($this->rpcProxy != null && is_callable([$this->rpcProxy, $func])) {
                $context = $this->rpcProxy;
            }
            if (!is_callable([$context, $func])) {
                $class = get_class($context);
                $result = new \Exception("$func 方法名 在 $class 中不存在");
            } else {
                $result = \co::call_user_func_array([$context, $func], $message['arg']);
            }
            if (!$message['oneWay']) {
                $newMessage['result'] = $result;
                $newMessage['token'] = $message['token'];
                $data = get_instance()->packServerMessageBody(SwooleMarco::PROCESS_RPC_RESULT, $newMessage);
                $this->sendMessage($data, $message['worker_id']);
            }
        });
    }

    /**
     * @param $data
     * @param $worker_id
     */
    protected function sendMessage($data, $worker_id)
    {
        if (get_instance()->isUserProcess($worker_id)) {
            $process = ProcessManager::getInstance()->getProcessFromID($worker_id);
            if ($process == null) return;
            if ($worker_id == get_instance()->workerId) {
                $process->readData($data);
            } else {
                $process->process->write(\swoole_serialize::pack($data));
            }
        } else {
            if ($worker_id == get_instance()->workerId) {
                get_instance()->onSwoolePipeMessage(get_instance()->server, $worker_id, $data);
            } else {
                get_instance()->server->sendMessage($data, $worker_id);
            }
        }
    }
}
