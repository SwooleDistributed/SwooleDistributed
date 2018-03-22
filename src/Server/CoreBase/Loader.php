<?php
/**
 * Loader 加载器
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午12:21
 */

namespace Server\CoreBase;

use Server\Components\AOP\AOP;
use Server\Memory\Pool;

class Loader implements ILoader
{
    private $_task_proxy;
    private $_model_factory;

    public function __construct()
    {
        $this->_task_proxy = new TaskProxy();
        $this->_model_factory = ModelFactory::getInstance();
    }

    /**
     * 获取一个model
     * @param $model
     * @param Child $parent
     * @return mixed|null
     * @throws SwooleException
     */
    public function model($model, Child $parent)
    {
        if (empty($model)) {
            return null;
        }
        $root = $parent;
        while (isset($root)) {
            if ($model == $root->core_name) {
                return AOP::getAOP($root);
            }
            if ($root->hasChild($model)) {
                return AOP::getAOP($root->getChild($model));
            }
            $root = $root->parent ?? null;
        }
        $model_instance = $this->_model_factory->getModel($model);
        $parent->addChild($model_instance);
        $model_instance->initialization($parent->getContext());
        return AOP::getAOP($model_instance);
    }

    /**
     * 获取一个task
     * @param $task
     * @param Child $parent
     * @return mixed|null|TaskProxy
     * @throws SwooleException
     */
    public function task($task, Child $parent = null)
    {
        if (empty($task)) {
            return null;
        }
        if (class_exists($task)) {
            $task_class = $task;
        } else {
            $task = str_replace('/', '\\', $task);
            $task_class = "app\\Tasks\\" . $task;
            if (!class_exists($task_class)) {
                $task_class = "Server\\Tasks\\" . $task;
                if (!class_exists($task_class)) {
                    throw new SwooleException("class task_class not exists");
                }
            }
        }
        if (!get_instance()->server->taskworker) {//工作进程返回taskproxy
            $this->_task_proxy->core_name = $task_class;
            if ($parent != null) {
                $this->_task_proxy->setContext($parent->getContext());
            }
            return AOP::getAOP($this->_task_proxy);
        }
        $task_instance = Pool::getInstance()->get($task_class);
        $task_instance->reUse();
        return $task_instance;
    }

    /**
     * view 返回一个模板
     * @param $template
     * @param array $data
     * @param array $mergeData
     * @return string
     */
    public function view($template, $data = [], $mergeData = [])
    {
        $template = get_instance()->templateEngine->render($template, $data, $mergeData);
        return $template;
    }

    /**
     * @return \Illuminate\Contracts\View\Factory
     */
    public function getViewFactory()
    {
        return get_instance()->getTemplateEngine()->viewFactory();
    }
}