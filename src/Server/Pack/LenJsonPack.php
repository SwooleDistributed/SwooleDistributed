<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午2:43
 */

namespace Server\Pack;

use Server\CoreBase\SwooleException;

class LenJsonPack implements IPack
{
    protected $package_length_type = 'N';
    protected $package_length_type_length = 4;
    protected $package_length_offset = 0;
    protected $package_body_offset = 0;

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
        $total_length = $this->package_length_type_length + strlen($buffer) - $this->package_body_offset;
        return pack($this->package_length_type, $total_length) . $buffer;
    }

    /**
     * @param $buffer
     * @return string
     */
    public function decode($buffer)
    {
        return substr($buffer, $this->package_length_type_length);
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
            'open_length_check' => true,
            'package_length_type' => $this->package_length_type,
            'package_length_offset' => $this->package_length_offset,       //第N个字节是包长度的值
            'package_body_offset' => $this->package_body_offset,       //第几个字节开始计算长度
            'package_max_length' => 2000000,  //协议最大长度)
        ];
    }

    public function errorHandle(\Throwable $e, $fd)
    {
        get_instance()->close($fd);
    }
}