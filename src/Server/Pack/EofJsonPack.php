<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午2:43
 */

namespace Server\Pack;

use Server\CoreBase\SwooleException;

class EofJsonPack implements IPack
{
    protected $package_eof = "\r\n";

    protected $last_data = null;
    protected $last_data_result = null;

    /**
     * 数据包编码
     * @param $buffer
     * @return string
     * @throws SwooleException
     */
    public function encode($buffer)
    {
        return $buffer . $this->package_eof;
    }

    /**
     * @param $buffer
     * @return string
     */
    public function decode($buffer)
    {
        $data = str_replace($this->package_eof, '', $buffer);
        return $data;
    }

    public function pack($data, $topic = null)
    {
        if ($this->last_data != null && $this->last_data == $data) {
            return $this->last_data_result;
        }
        $this->last_data = $data;
        $this->last_data_result = $this->encode(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $this->last_data_result;
    }

    public function unPack($data)
    {
        $value = json_decode($this->decode($data));
        if (empty($value)) {
            throw new SwooleException('json unPack 失败');
        }
        return $value;
    }

    public function getProbufSet()
    {
        return [
            'open_eof_split' => true,
            'package_eof' => $this->package_eof,
            'package_max_length' => 2000000
        ];
    }

    public function errorHandle(\Throwable $e, $fd)
    {
        get_instance()->close($fd);
    }
}