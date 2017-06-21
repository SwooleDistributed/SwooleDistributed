<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Exception;
use Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Utility;


/**
 * Message UNSUBSCRIBE
 * Client -> Server
 *
 * 3.10 UNSUBSCRIBE – Unsubscribe from topics
 */
class UNSUBSCRIBE extends Base
{
    protected $message_type = Message::UNSUBSCRIBE;
    protected $protocol_type = self::WITH_PAYLOAD;

    protected $topics = array();

    public function addTopic($topic_filter)
    {
        Utility::CheckTopicFilter($topic_filter);
        $this->topics[] = $topic_filter;
    }

    protected function payload()
    {
        if (empty($this->topics)) {
            /*
             The Topic Filters in an UNSUBSCRIBE packet MUST be UTF-8 encoded strings as
             defined in Section 1.5.3, packed contiguously [MQTT-3.10.3-1].
             */
            throw new Exception('Missing topics!');
        }

        $buffer = "";

        # Payload
        foreach ($this->topics as $topic) {
            $buffer .= Utility::PackStringWithLength($topic);
        }

        return $buffer;
    }
}

# EOF