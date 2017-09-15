<?php

/**
 * MQTT Client
 */


namespace Server\Asyn\MQTT;

/**
 * Message type definitions
 */
class Message
{
    /**
     * Message Type: CONNECT
     */
    const CONNECT = 0x01;
    /**
     * Message Type: CONNACK
     */
    const CONNACK = 0x02;
    /**
     * Message Type: PUBLISH
     */
    const PUBLISH = 0x03;
    /**
     * Message Type: PUBACK
     */
    const PUBACK = 0x04;
    /**
     * Message Type: PUBREC
     */
    const PUBREC = 0x05;
    /**
     * Message Type: PUBREL
     */
    const PUBREL = 0x06;
    /**
     * Message Type: PUBCOMP
     */
    const PUBCOMP = 0x07;
    /**
     * Message Type: SUBSCRIBE
     */
    const SUBSCRIBE = 0x08;
    /**
     * Message Type: SUBACK
     */
    const SUBACK = 0x09;
    /**
     * Message Type: UNSUBSCRIBE
     */
    const UNSUBSCRIBE = 0x0A;
    /**
     * Message Type: UNSUBACK
     */
    const UNSUBACK = 0x0B;
    /**
     * Message Type: PINGREQ
     */
    const PINGREQ = 0x0C;
    /**
     * Message Type: PINGRESP
     */
    const PINGRESP = 0x0D;
    /**
     * Message Type: DISCONNECT
     */
    const DISCONNECT = 0x0E;

    static public $name = array(
        Message::CONNECT => 'CONNECT',
        Message::CONNACK => 'CONNACK',
        Message::PUBLISH => 'PUBLISH',
        Message::PUBACK => 'PUBACK',
        Message::PUBREC => 'PUBREC',
        Message::PUBREL => 'PUBREL',
        Message::PUBCOMP => 'PUBCOMP',
        Message::SUBSCRIBE => 'SUBSCRIBE',
        Message::SUBACK => 'SUBACK',
        Message::UNSUBSCRIBE => 'UNSUBSCRIBE',
        Message::UNSUBACK => 'UNSUBACK',
        Message::PINGREQ => 'PINGREQ',
        Message::PINGRESP => 'PINGRESP',
        Message::DISCONNECT => 'DISCONNECT',
    );

    /**
     * Create Message Object
     *
     * @param int $message_type
     * @param IMqtt $mqtt
     * @return mixed
     * @throws Exception
     */
    static public function Create($message_type, IMqtt $mqtt)
    {
        if (!isset(Message::$name[$message_type])) {
            throw new Exception('Message type not defined');
        }

        $class = __NAMESPACE__ . '\\Message\\' . self::$name[$message_type];

        return new $class($mqtt);
    }

    /**
     * Maximum remaining length
     */
    const MAX_DATA_LENGTH = 268435455;
}

# EOF