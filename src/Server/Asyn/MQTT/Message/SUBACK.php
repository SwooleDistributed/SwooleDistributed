<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Message;


/**
 * Message SUBACK
 * Client <- Server
 *
 * 3.9 SUBACK – Subscribe acknowledgement
 */
class SUBACK extends Base
{
    protected $message_type = Message::SUBACK;
    protected $protocol_type = self::WITH_VARIABLE;
    protected $read_bytes = 4;

    /**
     * Return Codes from SUBACK Payload
     *
     * @var array
     */
    protected $return_codes = array();

    /**
     * Get return codes
     *
     * @return array
     */
    public function getReturnCodes()
    {
        return $this->return_codes;
    }

    public function setReturnCodes($codes)
    {
        $this->return_codes = $codes;
    }
    /**
     * Decode Payload
     *
     * @param string & $packet_data
     * @param int    & $payload_pos
     * @return void
     */
    protected function decodePayload(& $packet_data, & $payload_pos)
    {
        $return_code = array();

        while (isset($packet_data[$payload_pos])) {
            $return_code[] = ord($packet_data[$payload_pos]);

            ++ $payload_pos;
        }

        $this->return_codes = $return_code;
    }

    protected function payload()
    {
        $buffer = "";

        # Payload
        foreach ($this->return_codes as $qos_max) {
            $buffer .= chr($qos_max);
        }

        return $buffer;
    }
}

# EOF