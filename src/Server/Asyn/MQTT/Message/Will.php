<?php

/**
 * MQTT Client
 */
namespace Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Exception;
use Server\Asyn\MQTT\Utility;

/**
 * Connect Will
 *
 */
class Will
{
    /**
     * Will Retain
     *
     * @var int
     */
    protected $retain = 0;
    /**
     * Will QoS
     *
     * @var int
     */
    protected $qos = 0;
    /**
     * Will Topic
     *
     * @var string
     */
    protected $topic = '';
    /**
     * Will Message
     *
     * @var string
     */
    protected $message = '';

    /**
     * Will
     *
     * @param string $topic     Will Topic
     * @param string $message   Will Message
     * @param int    $qos       Will QoS
     * @param int    $retain    Will Retain
     * @throws Exception
     */
    public function __construct($topic, $message, $qos=1, $retain=0)
    {
        /*
         If the Will Flag is set to 0 the Will QoS and Will Retain fields in the Connect Flags
         MUST be set to zero and the Will Topic and Will Message fields MUST NOT be present in
         the payload [MQTT-3.1.2-11].
         */

        if (!$topic || !$message) {
            throw new Exception('Topic/Message MUST NOT be empty in Will Message');
        }

        Utility::CheckTopicName($topic);

        $this->topic   = $topic;
        $this->message = $message;

        Utility::CheckQoS($qos);
        $this->qos     = (int) $qos;
        $this->retain  = $retain ? 1 : 0;
    }

    /**
     * Get Will Topic
     *
     * @return string
     */
    public function getTopic()
    {
        return $this->topic;
    }

    /**
     * Get Will Message
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get Will flags
     *
     * @return int
     */
    public function get()
    {
        $var = 0;
        # Will flag
        $var |= 0x04;
        # Will QoS
        $var |= $this->qos << 3;
        # Will RETAIN
        if ($this->retain) {
            $var |= 0x20;
        }

        return $var;
    }
}

# EOF