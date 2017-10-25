<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message;

use Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Utility;

/**
 * Message CONNECT
 * Client -> Server
 *
 * 3.1 CONNECT – Client requests a connection to a Server
 *
 * @property header\CONNECT $header
 */
class CONNECT extends Base
{
    protected $message_type = Message::CONNECT;
    protected $protocol_type = self::WITH_PAYLOAD;

    /**
     * Connect Will
     *
     * @var Will
     */
    public $will;

    /**
     * Username
     *
     * @var string
     */
    public $username = '';

    /**
     * Password
     *
     * @var string
     */
    public $password = '';
    /**
     * @var string
     */
    public $client_id;

    /**
     * Clean Session
     *
     * @param int $clean
     */
    public function setClean($clean)
    {
        $this->header->setClean($clean);
    }

    public function getClean()
    {
        return $this->header->getClean();
    }

    public function getKeepAlive()
    {
        return $this->header->getKeepAlive();
    }

    public function getWillFlag()
    {
        return $this->header->getWillFlag();
    }

    public function getWillQos()
    {
        return $this->header->getWillQos();
    }

    public function getWillRetain()
    {
        return $this->header->getWillRetain();
    }

    public function getWillTopic()
    {
        return $this->will->getTopic();
    }

    public function getWillMessage()
    {
        return $this->will->getMessage();
    }

    public function getUserNameFlag()
    {
        return $this->header->getUserNameFlag();
    }

    public function getPassWordFlag()
    {
        return $this->header->getPassWordFlag();
    }
    /**
     * Connect Will
     *
     * @param Will $will
     */
    public function setWill(Will $will)
    {
        $this->will = $will;
    }

    /**
     * Keep Alive
     *
     * @param int $keepalive
     */
    public function setKeepalive($keepalive)
    {
        $this->header->setKeepalive($keepalive);
    }

    public function setClientId($clientId)
    {
        $this->client_id = $clientId;
    }
    /**
     * Username and Password
     *
     * @param string $username
     * @param string $password
     */
    public function setAuth($username, $password = '')
    {
        $this->username = $username;
        $this->password = $password;
    }

    protected function decodePayload(& $packet_data, & $payload_pos)
    {
        $message = substr($packet_data, $payload_pos);
        $messages = $this->readUTF($message);
        $this->client_id = array_shift($messages);
        if ($this->header->getWillFlag()) {
            $this->will = new Will(array_shift($messages),
                array_shift($messages),
                $this->header->getWillQos(),
                $this->header->getWillRetain());
        }
        if ($this->header->getUserNameFlag()) {
            $this->username = array_shift($messages);
        }
        if ($this->header->getPassWordFlag()) {
            $this->password = array_shift($messages);
        }
    }

    protected function payload()
    {
        $payload = '';

        $payload .= Utility::PackStringWithLength($this->client_id);

        # Adding Connect Will
        if ($this->will && $this->will->get()) {
            /*
             If the Will Flag is set to 0 the Will QoS and Will Retain fields in the Connect Flags
             MUST be set to zero and the Will Topic and Will Message fields MUST NOT be present in
             the payload [MQTT-3.1.2-11].
             */
            $payload .= Utility::PackStringWithLength($this->will->getTopic());
            $payload .= Utility::PackStringWithLength($this->will->getMessage());
        }

        # Append Username
        if ($this->username != NULL) {
            $payload .= Utility::PackStringWithLength($this->username);
        }
        # Append Password
        if ($this->password != NULL) {
            $payload .= Utility::PackStringWithLength($this->password);
        }

        return $payload;
    }
}

# EOF