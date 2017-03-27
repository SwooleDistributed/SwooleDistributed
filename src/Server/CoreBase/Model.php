<?php
namespace Server\CoreBase;
/**
 * Model 涉及到数据有关的处理
 * 对象池模式，实例会被反复使用，成员变量缓存数据记得在销毁时清理
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午12:00
 */
class Model extends CoreBase
{
    final public function __construct()
    {
        parent::__construct();
    }

    /**
     * 当被loader时会调用这个方法进行初始化
     */
    public function initialization(&$context)
    {
        $this->setContext($context);
    }

    /**
     * 销毁回归对象池
     */
    public function destroy()
    {
        parent::destroy();
        ModelFactory::getInstance()->revertModel($this);
    }

}