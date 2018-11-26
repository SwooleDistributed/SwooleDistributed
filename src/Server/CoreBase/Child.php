<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-29
 * Time: 上午11:05
 */

namespace Server\CoreBase;


use Server\Components\AOP\AOP;
use Server\Components\AOP\Proxy;

class Child extends AOP
{
    /**
     * 名称
     * @var string
     */
    public $core_name;

    /**
     * @var
     */

    public $parent;
    /**
     * 子集
     * @var array
     */
    public $child_list = [];

    /**
     * 上下文
     * @var array
     */
    protected $context = [];

    /**
     * @var Proxy
     */
    protected $proxy;

    public function __construct($proxy = ChildProxy::class)
    {
        parent::__construct($proxy);
    }

    /**
     * 加入一个插件
     * @param $child Child
     */
    public function addChild($child)
    {
        if ($child == null) return;
        $child->onAddChild($this);
        $this->child_list[$child->core_name] = $child;
    }

    /**
     * 被加入列表时
     * @param $parent
     */
    public function onAddChild($parent)
    {
        $this->parent = $parent;
    }

    /**
     * 是否存在插件
     * @param $name
     * @return bool
     */
    public function hasChild($name)
    {
        return array_key_exists($name, $this->child_list);
    }

    /**
     * 获取插件
     * @param $name
     * @return mixed|null
     */
    public function getChild($name)
    {
        return $this->child_list[$name]??null;
    }

    /**
     * 获取上下文
     * @return array
     */
    public function &getContext()
    {
        return $this->context;
    }

    /**
     * 设置上下文
     * @param $context
     */
    public function setContext(&$context)
    {
        $this->context = &$context;
    }

    /**
     * 销毁，解除引用
     */
    public function destroy()
    {
        foreach ($this->child_list as $core_child) {
            $core_child->getProxy()->destroy();
        }
        $this->child_list = [];
        $this->parent = null;
    }
}