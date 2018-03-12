<?php
namespace Server\CoreBase;
/**
 * Model工厂模式
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午12:03
 */
class ModelFactory
{
    /**
     * @var ModelFactory
     */
    private static $instance;
    private $pool = [];
    private $pool_count = [];

    /**
     * ModelFactory constructor.
     */
    public function __construct()
    {
        self::$instance = $this;
    }

    /**
     * 获取单例
     * @return ModelFactory
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            new ModelFactory();
        }
        return self::$instance;
    }

    /**
     * 获取一个model
     * @param $old_model
     * @return mixed
     * @throws SwooleException
     */
    public function getModel($old_model)
    {
        $model = str_replace('/', '\\', $old_model);
        if (!array_key_exists($old_model, $this->pool)) {
            $this->pool[$old_model] = new \SplStack();;
        }
        if (!$this->pool[$old_model]->isEmpty()) {
            $model_instance = $this->pool[$old_model]->shift();
            $model_instance->reUse();
            return $model_instance;
        }
        if (class_exists($model)) {
            $model_instance = new $model;
            if ($model_instance instanceof Model) {
                $model_instance->core_name = $old_model;
                $this->addNewCount($old_model);
                return $model_instance;
            }
        }

        $class_name = "app\\Models\\$model";

        if (class_exists($class_name)) {
            $model_instance = new $class_name;
            $model_instance->core_name = $old_model;
            $this->addNewCount($old_model);
        } else {
            $class_name = "Server\\Models\\$model";
            if (class_exists($class_name)) {
                $model_instance = new $class_name;
                $model_instance->core_name = $old_model;
                $this->addNewCount($old_model);
            } else {
                throw new SwooleException("class $model is not exist");
            }
        }
        return $model_instance;
    }

    /**
     * 归还一个model
     * @param $model Model
     */
    public function revertModel($model)
    {
        if (!$model->is_destroy) {
            $model->destroy();
        }
        $this->pool[$model->core_name]->push($model);
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