<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message\Header;
use Server\Asyn\MQTT\Debug;
use Server\Asyn\MQTT\Exception;


/**
 * Fixed Header definition for CONNACK
 */
class CONNACK extends Base
{
    /**
     * Default Flags
     *
     * @var int
     */
    protected $reserved_flags = 0x00;

    /**
     * CONNACK does not have Packet Identifier
     *
     * @var bool
     */
    protected $require_msgid = false;

    /**
     * Session Present
     *
     * @var int
     */
    protected $session_present = 0;

    /**
     * Connect Return code
     *
     * @var int
     */
    protected $return_code = 0;

    /**
     * Default error definitions
     *
     * @var array
     */
    static public $connect_errors = array(
        0   =>  'Connection Accepted',
        1   =>  'Connection Refused: unacceptable protocol version',
        2   =>  'Connection Refused: identifier rejected',
        3   =>  'Connection Refused: server unavailable',
        4   =>  'Connection Refused: bad user name or password',
        5   =>  'Connection Refused: not authorized',
    );

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
        $this->session_present = ord($packet_data[2]) & 0x01;

        $this->return_code = ord($packet_data[3]);

        if ($this->return_code != 0) {
            $error = isset(self::$connect_errors[$this->return_code]) ? self::$connect_errors[$this->return_code] : 'Unknown error';
            Debug::Log(
                Debug::ERR,
                sprintf(
                    "Connection failed! (Error: 0x%02x 0x%02x|%s)",
                    ord($packet_data[2]),
                    $this->return_code,
                    $error
                )
            );

            /*
             If a server sends a CONNACK packet containing a non-zero return code it MUST
             then close the Network Connection [MQTT-3.2.2-5]
             */
            throw new Exception\ConnectError($error);
        }

        if ($this->session_present) {
            Debug::Log(Debug::DEBUG, "CONNACK: Session Present Flag: ON");
        } else {
            Debug::Log(Debug::DEBUG, "CONNACK: Session Present Flag: OFF");
        }
    }

    /**
     * Build Variable Header
     *
     * @return string
     */
    protected function buildVariableHeader()
    {
        $buffer = "";
        $buffer .= chr($this->session_present);
        $buffer .= chr($this->return_code);
        return $buffer;
    }

    /**
     * @param $return_code
     */
    public function setReturnCode($return_code)
    {
        $this->return_code = $return_code;
    }

    public function setSessionPresent($session_present)
    {
        $this->session_present = $session_present;
    }
}

# EOF