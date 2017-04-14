<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 上午11:35
 */
namespace Server\CoreBase;
use Exception;

/**
 * 重定向
 * Class SwooleRedirectException
 * @package Server\CoreBase
 */
class SwooleRedirectException extends \Exception
{
    public function __construct($location, $code, Exception $previous = null)
    {
        parent::__construct($location, $code, $previous);
    }
}