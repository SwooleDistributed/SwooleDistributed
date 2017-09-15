<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Exception;
use Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Utility;


/**
 * Message SUBSCRIBE
 * Client -> Server
 *
 * 3.8 SUBSCRIBE - Subscribe to topics
 */
class SUBSCRIBE extends Base
{
    protected $message_type = Message::SUBSCRIBE;
    protected $protocol_type = self::WITH_PAYLOAD;

    protected $topics = array();

    public function addTopic($topic_filter, $qos_max)
    {
        Utility::CheckTopicFilter($topic_filter);
        Utility::CheckQoS($qos_max);
        $this->topics[$topic_filter] = $qos_max;
    }

    public function getTopic()
    {
        return $this->topics;
    }
    protected function payload()
    {
        if (empty($this->topics)) {
            /*
             The payload of a SUBSCRIBE packet MUST contain at least one Topic Filter / QoS pair.
             A SUBSCRIBE packet with no payload is a protocol violation [MQTT-3.8.3-3]
             */
            throw new Exception('Missing topics!');
        }

        $buffer = "";

        # Payload
        foreach ($this->topics as $topic=>$qos_max) {
            $buffer .= Utility::PackStringWithLength($topic);
            $buffer .= chr($qos_max);
        }

        return $buffer;
    }

    protected function decodePayload(& $packet_data, & $payload_pos)
    {
        while (isset($packet_data[$payload_pos])) {
            $topic = Utility::UnpackStringWithLength($packet_data, $payload_pos);
            $qos = ord($packet_data[$payload_pos]);
            $this->topics[$topic] = $qos;
            ++$payload_pos;
        }
    }
}

# EOF