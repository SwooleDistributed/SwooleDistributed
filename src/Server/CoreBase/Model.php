<?php
namespace Server\CoreBase;
/**
 * Model 涉及到数据有关的处理
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午12:00
 */
class Model extends CoreBase
{
    public function __construct()
    {
        parent::__construct();
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