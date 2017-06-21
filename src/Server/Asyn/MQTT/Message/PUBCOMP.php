<?php
/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Message;


/**
 * Message PUBCOMP
 * Client <-> Server
 */
class PUBCOMP extends Base
{
    protected $message_type = Message::PUBCOMP;
    protected $protocol_type = self::WITH_VARIABLE;
    protected $read_bytes = 4;

}

# EOF