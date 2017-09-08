<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 上午11:35
 */
namespace Server\CoreBase;
class SwooleException extends \Exception
{
    /**
     * @var string
     */
    public $others;

    public function __construct($message, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * 设置追加信息
     * @param $others
     */
    public function setShowOther($others)
    {
        $this->others = $others;
    }
}