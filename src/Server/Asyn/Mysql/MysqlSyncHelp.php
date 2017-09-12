<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-9-11
 * Time: 上午9:15
 */

namespace Server\Asyn\Mysql;


use ArrayAccess;

class MysqlSyncHelp implements ArrayAccess
{
    private $elements;
    private $mysql;

    public function __construct($mysql, $data)
    {
        $this->mysql = $mysql;
        $this->elements = $data;
    }

    public function dump()
    {
        print_r("[dump] " . $this->mysql . "\n");
        return $this;
    }

    public function offsetExists($offset)
    {
        return isset($this->elements[$offset]);
    }

    public function offsetSet($offset, $value)
    {
        $this->elements[$offset] = $value;
    }

    public function offsetGet($offset)
    {
        return $this->elements[$offset];
    }

    public function offsetUnset($offset)
    {
        unset($this->elements[$offset]);
    }
}