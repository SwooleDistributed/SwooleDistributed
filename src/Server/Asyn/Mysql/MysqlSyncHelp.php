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
        secho("MYSQL", $this->mysql);
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

    /**
     * @return mixed
     */
    public function result_array()
    {
        return $this->elements['result'];
    }

    /**
     * @param $index
     * @return null
     */
    public function row_array($index)
    {
        return $this->elements['result'][$index] ?? null;
    }

    /**
     * @return null
     */
    public function row()
    {
        return $this->elements['result'][0] ?? null;
    }

    /**
     * @return int
     */
    public function num_rows()
    {
        return count($this->elements['result']);
    }

    /**
     * @return mixed
     */
    public function insert_id()
    {
        return $this->elements['insert_id'];
    }
}