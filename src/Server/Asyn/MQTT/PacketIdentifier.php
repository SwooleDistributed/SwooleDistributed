<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT;

/**
 * Packet Identifier Generator
 *
 * @package Server\Asyn\MQTT
 */
class PacketIdentifier
{
    /**
     * @var ifPacketIdentifierStore
     */
    protected $pi;

    public function __construct()
    {
        $this->pi = new PacketIdentifierStore_Static();
    }

    /**
     * Next Packet Identifier
     *
     * @return int
     */
    public function next()
    {
        return $this->pi->next() % 65535 + 1;
    }

    /**
     * Current Packet Identifier
     *
     * @return mixed
     */
    public function get()
    {
        return $this->pi->get() % 65535 + 1;
    }

    /**
     * Set A New ID
     *
     * @param int $new_id
     * @return void
     */
    public function set($new_id)
    {
        $this->pi->set($new_id);
    }
}

/**
 * Interface ifPacketIdentifierStore
 *
 * @package Server\Asyn\MQTT
 */
interface ifPacketIdentifierStore
{
    /**
     * Get Current Packet Identifier
     *
     * @return int
     */
    public function get();

    /**
     * Next Packet Identifier
     *
     * @return int
     */
    public function next();

    /**
     * Set A New ID
     *
     * @param $new_id
     * @return void
     */
    public function set($new_id);
}

class PacketIdentifierStore_Static implements ifPacketIdentifierStore
{
    protected $id = 0;

    /**
     * Get Current Packet Identifier
     *
     * @return int
     */
    public function get()
    {
        return $this->id;
    }

    /**
     * Next Packet Identifier
     *
     * @return int
     */
    public function next()
    {
        return ++$this->id;
    }

    /**
     * Set A New ID
     *
     * @param $new_id
     * @return void
     */
    public function set($new_id)
    {
        $this->id = (int) $new_id;
    }
}

# EOF