<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message\Header;

use Server\Asyn\MQTT\Debug;
use Server\Asyn\MQTT\Exception;
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

    protected $will_flag;
    protected $will_qos;
    protected $will_retain;
    protected $user_name_flag;
    protected $password_flag;

    public function getClean()
    {
        return $this->clean;
    }

    public function getKeepAlive()
    {
        return $this->keepalive;
    }

    public function getWillFlag()
    {
        return $this->will_flag;
    }

    public function getWillQos()
    {
        return $this->will_qos;
    }

    public function getWillRetain()
    {
        return $this->will_retain;
    }

    public function getUserNameFlag()
    {
        return $this->user_name_flag;
    }

    public function getPassWordFlag()
    {
        return $this->password_flag;
    }

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
        $this->keepalive = (int)$keepalive;
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
     * @param int & $pos
     * @return bool
     * @throws Exception
     */
    protected function decodeVariableHeader(& $packet_data, & $pos)
    {
        Debug::Log(Debug::DEBUG, "CONNECT", $packet_data);
        $pos++;
        //Protocol Name
        $length = ord($packet_data[$pos]);
        $pos += $length + 1;
        //Protocol Level
        $level = ord($packet_data[$pos]);
        $pos++;
        //Connect Flags
        $flags = ord($packet_data[$pos]);
        $reserved = $flags & 0x01;
        $this->clean = ($flags & 0x02) >> 1;
        $this->will_flag = ($flags & 0x04) >> 2;
        $this->will_qos = ($flags & 0x18) >> 3;
        $this->will_retain = ($flags & 0x20) >> 5;
        $this->user_name_flag = ($flags & 0x80) >> 7;
        $this->password_flag = ($flags & 0x40) >> 6;
        $pos++;
        //Keep Alive
        $keep_alive_msb = ord($packet_data[$pos]);
        $pos++;
        $keep_alive_lsb = ord($packet_data[$pos]);
        $this->keepalive = $keep_alive_msb * 128 + $keep_alive_lsb;
        $pos++;
    }
}

# EOF