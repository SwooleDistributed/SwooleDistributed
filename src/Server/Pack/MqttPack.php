<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午2:43
 */

namespace Server\Pack;

use Server\Asyn\MQTT\Exception;
use Server\Asyn\MQTT\IMqtt;
use Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Message\Base;
use Server\Asyn\MQTT\MQTT;
use Server\Asyn\MQTT\Utility;
use Server\CoreBase\SwooleException;

class MqttPack implements IPack, IMqtt
{
    /**
     * 数据包编码
     * @param $buffer
     * @return string
     * @throws SwooleException
     */
    public function encode($buffer)
    {

    }

    /**
     * @param $buffer
     * @return string
     */
    public function decode($buffer)
    {

    }

    public function pack($data, $topic = null)
    {
        if ($data instanceof Base) {
            $data = $data->build();
        } else {
            $message = new Message\PUBLISH($this);
            $message->setTopic($topic);
            $message->setDup(0);
            $message->setQos(0);
            $message->setMessage($data);
            $data = $message->build();
        }
        return $data;
    }

    public function unPack($data)
    {
        //   Debug::Enable();
        $message_object = $this->message_read($data);
        $class = new \stdClass();
        $class->controller_name = 'MqttController';
        $class->params = [$message_object];
        switch ($message_object->getMessageType()) {
            case Message::CONNECT:
                $class->method_name = 'connect';
                break;
            case Message::PUBLISH:
                $class->method_name = 'publish';
                break;
            case Message::PUBREL:
                $class->method_name = 'pubrel';
                break;
            case Message::SUBSCRIBE:
                $class->method_name = 'subscribe';
                break;
            case Message::UNSUBSCRIBE:
                $class->method_name = 'unsubscribe';
                break;
            case Message::PINGREQ:
                $class->method_name = 'pingreq';
                break;
            case Message::DISCONNECT:
                $class->method_name = 'disconnect';
                break;
        }
        return $class;
    }

    public function getProbufSet()
    {
        return [
            'open_mqtt_protocol' => true,
            'package_max_length' => 2000000,  //协议最大长度
        ];
    }

    public function errorHandle(\Throwable $e, $fd)
    {
        get_instance()->close($fd);
    }

    /**
     * Read Message And Create Message Object
     *
     * @return \Server\Asyn\MQTT\Message\Base
     * @throws \Server\Asyn\MQTT\Exception
     */
    protected function message_read($data)
    {
        $cmd = Utility::ParseCommand(ord($data[0]));
        $message_type = $cmd['message_type'];
        $pos = 1;
        $remaining_length = Utility::DecodeLength($data, $pos);
        $message_object = $this->getMessageObject($message_type);
        $message_object->decode($data, $remaining_length);
        return $message_object;
    }

    /**
     * Create Message\Base object
     *
     * @param int $message_type
     * @return Message\Base
     * @throws Exception
     */
    public function getMessageObject($message_type)
    {
        return Message::Create($message_type, $this);
    }

    public function version()
    {
        return MQTT::VERSION_3_1_1;
    }
}