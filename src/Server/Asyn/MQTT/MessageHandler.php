<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT;

class MessageHandler
{

    public function connack(MQTT $mqtt, Message\CONNACK $connack_object)
    {

    }

    public function disconnect(MQTT $mqtt)
    {

    }

    public function suback(MQTT $mqtt, Message\SUBACK $suback_object)
    {

    }

    public function unsuback(MQTT $mqtt, Message\UNSUBACK $unsuback_object)
    {

    }

    public function publish(MQTT $mqtt, Message\PUBLISH $publish_object)
    {

    }

    public function puback(MQTT $mqtt, Message\PUBACK $puback_object)
    {

    }

    public function pubrec(MQTT $mqtt, Message\PUBREC $pubrec_object)
    {

    }

    public function pubrel(MQTT $mqtt, Message\PUBREL $pubrel_object)
    {

    }

    public function pubcomp(MQTT $mqtt, Message\PUBCOMP $pubcomp_object)
    {

    }

}

# EOF