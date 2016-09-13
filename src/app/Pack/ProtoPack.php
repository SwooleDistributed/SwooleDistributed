<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午2:43
 */

namespace Server\Pack;

use app\Protobuf\Message;

class ProtoPack implements IPack
{
    /**
     * @param $data Message
     * @return string
     */
    public function pack($data)
    {
        return $data->toStream()->getContents();
    }

    /**
     * @param $data string
     * @return mixed
     */
    public function unPack($data)
    {
        $message = new Message($data);
        $cmd_service = $message->getCmdService();
        $cmd_method = $message->getCmdMethod();
        $clientData = new \stdClass();
        $clientData->controller_name = $cmd_service->name();
        $clientData->method_name = 'proto/' . $cmd_method->name();
        $clientData->data = $message;
        return $clientData;
    }
}