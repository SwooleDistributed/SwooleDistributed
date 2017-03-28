<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-10-25
 * Time: 上午9:01
 */

namespace Server\Coroutine;

use Server\Memory\Pool;

/**
 * 生成器的上下文,记录协程运行过程
 * Class GeneratorContext
 * @package Server\CoreBase
 */
class GeneratorContext
{
    /**
     * @var Controller
     */
    protected $controller;
    protected $controller_name;
    protected $method_name;
    protected $stack;

    public function __construct()
    {

    }

    /**
     * 对象池模式代替__construct
     * @return $this
     */
    public function init()
    {
        $this->stack = [];
        return $this;
    }

    /**
     * @param $number
     */
    public function addYieldStack($number)
    {
        $number++;
        $i = count($this->stack);
        $this->stack[] = "| #第 $i 层嵌套出错在第 $number 个yield后";
    }

    /**
     *
     */
    public function popYieldStack()
    {
        array_pop($this->stack);
    }

    /**
     * @param $file
     * @param $line
     */
    public function setErrorFile($file, $line)
    {
        $this->stack[] = "| #出错文件： $file($line)";
    }

    /**
     * @param $message
     */
    public function setErrorMessage($message)
    {
        $this->stack[] = "| #报错消息:$message";
    }

    /**
     * @return Controller
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * 设置异常跟踪
     * @param $controller 出错会回调onExceptionHandle方法
     * @param $controller_name
     * @param $method_name
     */
    public function setController($controller, $controller_name, $method_name)
    {
        $this->controller = $controller;
        $this->controller_name = $controller_name;
        $this->method_name = $method_name;
        $this->stack[] = "| #目标函数： $controller_name -> $method_name";
    }

    /**
     * 获取堆打印
     */
    public function getTraceStack()
    {
        $trace = "------------协程错误指南------------\n";
        for ($i = 0; $i < count($this->stack); $i++) {
            $trace .= "{$this->stack[$i]}\n";
        }
        $trace .= "------------------------------------\n";
        return $trace;
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        $this->controller = null;
        $this->stack = null;
        Pool::getInstance()->push($this);
    }
}