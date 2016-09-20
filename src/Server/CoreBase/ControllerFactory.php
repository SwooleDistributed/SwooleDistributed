<?php
namespace Server\CoreBase;

/**
 * 控制器工厂模式
 * Created by PhpStorm.
 * User: tmtbe
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

    /**
     * ControllerFactory constructor.
     */
    public function __construct()
    {
        self::$instance = $this;
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
        if (!key_exists($controller, $this->pool)) {
            $this->pool[$controller] = new \SplQueue();
        }
        if (count($this->pool[$controller]) > 0) {
            $controller_instance = $this->pool[$controller]->shift();
            $controller_instance->reUse();
            return $controller_instance;
        }
        $class_name = "\\app\\Controllers\\$controller";
        if (class_exists($class_name)) {
            $controller_instance = new $class_name;
            $controller_instance->core_name = $controller;
            return $controller_instance;
        } else {
            $class_name = "\\Server\\Controllers\\$controller";
            if (class_exists($class_name)) {
                $controller_instance = new $class_name;
                $controller_instance->core_name = $controller;
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
}