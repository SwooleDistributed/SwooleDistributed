<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT\Message;
use Server\Asyn\MQTT\Utility;
use Server\Asyn\MQTT\Message;

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
     * Client Identifier
     *
     * @var string
     */
    protected $clientid = '';

    /**
     * Clean Session
     *
     * @param int $clean
     */
    public function setClean($clean)
    {
        $this->header->setClean($clean);
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

    /**
     * Username and Password
     *
     * @param string $username
     * @param string $password
     */
    public function setAuth($username, $password='')
    {
        $this->username = $username;
        $this->password = $password;
    }

    protected function payload()
    {
        $payload = '';

        $payload .= Utility::PackStringWithLength($this->mqtt->clientid);

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