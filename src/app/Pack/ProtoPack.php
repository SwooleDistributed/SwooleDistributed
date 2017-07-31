<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午2:43
 */

namespace Server\Pack;

use app\Protobuf\Message;
use Server\CoreBase\SwooleException;

class ProtoPack implements IPack
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

    /**
     * @param $data Message
     * @return string
     */
    public function pack($data)
    {
        if ($this->last_data != null && $this->last_data == $data) {
            return $this->last_data_result;
        }
        $this->last_data = $data;
        $this->last_data_result = $data->toStream()->getContents();
        return $this->last_data_result;
    }

    /**
     * @param $data string
     * @return mixed
     * @throws SwooleException
     */
    public function unPack($data)
    {
        if (empty($data)) {
            throw new SwooleException('unpack error');
        }
        $message = new Message($data);
        $cmd_service = $message->getCmdService();
        $cmd_method = $message->getCmdMethod();
        $clientData = new \stdClass();
        $clientData->controller_name = $cmd_service->name();
        $clientData->method_name = $cmd_method->name();
        $clientData->data = $message;
        $request = $message->getRequest()??null;
        if (empty($request)) {
            throw new SwooleException('unpack error');
        }
        $method = "getM{$clientData->method_name}Request";
        if (!method_exists($request, $method)) {
            throw new SwooleException('unpack method error');
        }
        $clientData->params = [call_user_func([$request, $method])];
        return $clientData;
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

    public function errorHandle($fd)
    {
        get_instance()->close($fd);
    }
}