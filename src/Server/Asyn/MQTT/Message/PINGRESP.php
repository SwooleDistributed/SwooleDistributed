<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Debug;
use Server\Asyn\MQTT\Utility;
use Server\Asyn\MQTT\Message;

/**
 * Message PINGRESP
 * Client <- Server
 *
 * 3.13 PINGRESP – PING response
 */
class PINGRESP extends Base
{
    protected $message_type = Message::PINGRESP;
    protected $protocol_type = self::FIXED_ONLY;
    protected $read_bytes = 2;
}

# EOF