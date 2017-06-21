<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Message;


/**
 * Message PUBREL
 * Client <-> Server
 *
 * 3.6 PUBREL – Publish release (QoS 2 publish received, part 2)
 */
class PUBREL extends Base
{
    protected $message_type = Message::PUBREL;
    protected $protocol_type = self::WITH_VARIABLE;
    protected $read_bytes = 4;

}

# EOF