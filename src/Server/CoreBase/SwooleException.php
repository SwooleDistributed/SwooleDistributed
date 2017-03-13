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
        //这里只打印，在controller里面写日志，能把context带进去。
        print_r($message . "\n");
    }

    /**
     * 设置追加信息
     * @param $others
     */
    public function setShowOther($others)
    {
        $this->others = $others;
        //这里只打印，在controller里面写日志，能把context带进去。
        print_r("=================================================\e[30;41m [ERROR] \e[0m==============================================================\n");
        if (!empty($others)) {
            print_r($others . "\n");
        } else {
            print_r($this->getMessage() . "\n");
            print_r($this->getTraceAsString() . "\n");
        }
        print_r("\n");
    }
}