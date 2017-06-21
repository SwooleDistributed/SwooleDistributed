<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message\Header;
use Server\Asyn\MQTT\Exception;
use Server\Asyn\MQTT\Message;


/**
 * Fixed Header definition for SUBSCRIBE
 */
class SUBSCRIBE extends Base
{
    /**
     * Default Flags
     *
     * @var int
     */
    protected $reserved_flags = 0x02;

    /**
     * SUBSCRIBE requires Packet Identifier
     *
     * @var bool
     */
    protected $require_msgid = true;

    /**
     * Decode Variable Header
     *
     * @param string & $packet_data
     * @param int    & $pos
     * @return bool
     * @throws Exception
     */
    protected function decodeVariableHeader(& $packet_data, & $pos)
    {
        throw new Exception('NO SUBSCRIBE will be sent to client');
    }
}

# EOF