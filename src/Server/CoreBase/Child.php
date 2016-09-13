<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-29
 * Time: 上午11:05
 */

namespace Server\CoreBase;


class Child
{
    public $parent;
    /**
     * 子集
     * @var array
     */
    public $child_list = [];

    /**
     * 加入一个插件
     * @param $child Child
     */
    public function addChild($child)
    {
        $child->onAddChild($this);
        array_push($this->child_list, $child);
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
     * 销毁，解除引用
     */
    public function destroy()
    {
        foreach ($this->child_list as $core_child) {
            $core_child->destroy();
        }
        $this->child_list = [];
        unset($this->parent);
    }

}