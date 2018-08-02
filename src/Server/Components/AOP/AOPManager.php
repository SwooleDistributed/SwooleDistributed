<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/1
 * Time: 10:43
 */

namespace Server\Components\AOP;


use Noodlehaus\Config;

class AOPManager
{
    private static $instance;
    private $aopConfig = [];
    private $cache = [];

    public function __construct(Config $config)
    {
        $aop_config = $config['aop'] ?? [];
        foreach ($aop_config as $one) {
            $key = str_replace("\\", "\.", $one['pointcut']);
            $key = str_replace("*", ".*", $key);
            $key = "#" . $key . "#";
            $one['pointcut'] = $key;
            $this->aopConfig[] = $one;
        }
    }

    /**
     * @param $class_name
     * @param $method_name
     * @return array
     */
    public function getAspects($class_name, $method_name)
    {
        $aspects = [];
        if (array_key_exists($class_name . "::" . $method_name, $this->cache)) {
            return $this->cache[$class_name . "::" . $method_name];
        }
        $pointcut = str_replace("\\", ".", $class_name . "::" . $method_name);
        foreach ($this->aopConfig as $value) {
            if ($this->match($pointcut, $value['pointcut'])) {//匹配了
                $aspects[] = $value;
            }
        }
        $this->cache[$class_name . "::" . $method_name] = $aspects;
        return $aspects;
    }

    private function match($name, $pointcut)
    {
        return preg_match($pointcut, $name) ? true : false;
    }

    public static function start()
    {
        if(empty(self::$instance)){
            self::$instance = new AOPManager(get_instance()->config);
        }
    }

    /**
     * @return AOPManager
     */
    public static function getInstance()
    {
        if(empty(self::$instance)){
            self::$instance = new AOPManager(get_instance()->config);
        }
        return self::$instance;
    }
}