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
    /**
     * @var Dispatch
     */
    public static $dispatch;
    public static function addDispatch($data)
    {
        self::$dispatch->addDispatch($data);
    }

    public static function removeDispatch($data)
    {
        self::$dispatch->removeDispatch($data);
    }

    public static function init($config)
    {
        if(self::$dispatch==null) {
            self::$dispatch = new Dispatch($config);
        }
    }
}