<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-9
 * Time: 下午12:01
 */

namespace Server\Components\Dispatch;

/**
 * Dispatch帮助
 * Class DispatchHelp
 * @package Server\CoreBase
 */
class DispatchHelp
{
    public static function addDispatch($data)
    {
        get_instance()->addDispatch($data);
    }

    public static function removeDispatch($data)
    {
        get_instance()->removeDispatch($data);
    }
}