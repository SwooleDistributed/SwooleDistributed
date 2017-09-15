<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message\Header;
use Server\Asyn\MQTT\Exception;


/**
 * Fixed Header definition for UNSUBSCRIBE
 */
class UNSUBSCRIBE extends Base
{
    /**
     * Default Flags
     *
     * @var int
     */
    protected $reserved_flags = 0x02;

    /**
     * UNSUBSCRIBE requires Packet Identifier
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
        return $this->decodePacketIdentifier($packet_data, $pos);
    }
}

# EOF