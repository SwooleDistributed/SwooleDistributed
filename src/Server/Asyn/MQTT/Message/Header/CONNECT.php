<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message\Header;
use Server\Asyn\MQTT\Exception;
use Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\MQTT;
use Server\Asyn\MQTT\Utility;


/**
 * Fixed Header definition for CONNECT
 *
 * @property \Server\Asyn\MQTT\Message\CONNECT $message
 */
class CONNECT extends Base
{
    /**
     * Default Flags
     *
     * @var int
     */
    protected $reserved_flags = 0x00;

    /**
     * CONNECT does not have Packet Identifier
     *
     * @var bool
     */
    protected $require_msgid = false;

    /**
     * Clean Session
     *
     * @var int
     */
    protected $clean = 1;

    /**
     * KeepAlive
     *
     * @var int
     */
    protected $keepalive = 60;

    /**
     * Clean Session
     *
     * Session is not stored currently.
     *
     * @todo Store Session  MQTT-3.1.2-4, MQTT-3.1.2-5
     * @param int $clean
     */
    public function setClean($clean)
    {
        $this->clean = $clean ? 1 : 0;
    }

    /**
     * Keep Alive
     *
     * @param int $keepalive
     */
    public function setKeepalive($keepalive)
    {
        $this->keepalive = (int) $keepalive;
    }

    /**
     * Build Variable Header
     *
     * @return string
     */
    protected function buildVariableHeader()
    {
        $buffer = "";

        # Protocol Name
        if ($this->message->mqtt->version() == MQTT::VERSION_3_1_1) {
            $buffer .= Utility::PackStringWithLength('MQTT');

        } else {
            $buffer .= Utility::PackStringWithLength('MQIsdp');
        }
        # End of Protocol Name

        # Protocol Level
        $buffer .= chr($this->message->mqtt->version());

        # Connect Flags
        # Set to 0 by default
        $var = 0;
        # clean session
        if ($this->clean) {
            $var |= 0x02;
        }
        # Will flags
        if ($this->message->will) {
            $var |= $this->message->will->get();
        }

        # User name flag
        if ($this->message->username != NULL) {
            $var |= 0x80;
        }
        # Password flag
        if ($this->message->password != NULL) {
            $var |= 0x40;
        }

        $buffer .= chr($var);
        # End of Connect Flags

        # Keep alive: unsigned short 16bits big endian
        $buffer .= pack('n', $this->keepalive);

        return $buffer;
    }

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
        throw new Exception('NO CONNECT will be sent to client');
    }
}

# EOF