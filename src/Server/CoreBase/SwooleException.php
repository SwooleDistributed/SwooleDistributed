<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 上午11:35
 */
namespace Server\CoreBase;
class SwooleException extends \Exception
{
    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        print_r($message . "\n");
        get_instance()->log->warning($message);
        parent::__construct($message, $code, $previous);
    }
}