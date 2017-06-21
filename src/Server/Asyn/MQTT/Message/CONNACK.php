<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Debug;
use Server\Asyn\MQTT\Message;


/**
 * Message CONNACK
 * Client <- Server
 *
 * 3.2 CONNACK – Acknowledge connection request
 *
 */
class CONNACK extends Base
{
    protected $message_type = Message::CONNACK;
    protected $protocol_type = self::WITH_VARIABLE;
    protected $read_bytes = 4;
}

# EOF