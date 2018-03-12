<?php

namespace Server\CoreBase;

/**
 * 控制器工厂模式
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午12:03
 */
class ControllerFactory
{
    /**
     * @var ControllerFactory
     */
    private static $instance;
    private $pool = [];
    private $pool_count = [];
    private $allow_ServerController = true;

    /**
     * ControllerFactory constructor.
     */
    public function __construct()
    {
        self::$instance = $this;
        $this->allow_ServerController = get_instance()->config->get('allow_ServerController', "true");
    }

    /**
     * 获取单例
     * @return ControllerFactory
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            new ControllerFactory();
        }
        return self::$instance;
    }

    /**
     * 获取一个Controller
     * @param $controller string
     * @return Controller
     */
    public function getController($controller)
    {
        if ($controller == null) return null;
        $controllers = $this->pool[$controller] ?? null;
        if ($controllers == null) {
            $controllers = $this->pool[$controller] = new \SplQueue();
        }
        if (!$controllers->isEmpty()) {
            $controller_instance = $controllers->shift();
            $controller_instance->reUse();
            return $controller_instance;
        }
        if (class_exists($controller)) {
            $controller_instance = new $controller;
            if ($controller_instance instanceof Controller) {
                $controller_instance->core_name = $controller;
                $this->addNewCount($controller);
                return $controller_instance;
            }
        }
        $controller_new = str_replace('/', '\\', $controller);
        $class_name = "app\\Controllers\\$controller_new";
        if (class_exists($class_name)) {
            $controller_instance = new $class_name;
            $controller_instance->core_name = $controller;
            $this->addNewCount($controller);
            return $controller_instance;
        } else {
            if (!$this->allow_ServerController) {
                return null;
            }
            $class_name = "Server\\Controllers\\$controller_new";
            if (class_exists($class_name)) {
                $controller_instance = new $class_name;
                $controller_instance->core_name = $controller;
                $this->addNewCount($controller);
                return $controller_instance;
            } else {
                return null;
            }
        }
    }

    /**
     * 归还一个controller
     * @param $controller Controller
     */
    public function revertController($controller)
    {
        if (!$controller->is_destroy) {
            $controller->destroy();
        }
        $this->pool[$controller->core_name]->push($controller);
    }

    private function addNewCount($name)
    {
        if (isset($this->pool_count[$name])) {
            $this->pool_count[$name]++;
        } else {
            $this->pool_count[$name] = 1;
        }
    }

    /**
     * 获取状态
     */
    public function getStatus()
    {
        $status = [];

        foreach ($this->pool as $key => $value) {
            $status[$key . '[pool]'] = count($value);
            $status[$key . '[new]'] = $this->pool_count[$key] ?? 0;
        }
        return $status;
    }
}