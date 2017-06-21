<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Message;


/**
 * Message DISCONNECT
 * Client -> Server
 *
 * 3.14 DISCONNECT – Disconnect notification
 */
class DISCONNECT extends Base
{
    protected $message_type = Message::DISCONNECT;
    protected $protocol_type = self::FIXED_ONLY;
}

# EOF