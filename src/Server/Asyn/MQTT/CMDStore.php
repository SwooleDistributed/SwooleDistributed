<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT;

/**
 * Class CMDStore
 *
 * @package Server\Asyn\MQTT
 */
class CMDStore
{

    protected $command_awaits = array();
    protected $command_awaits_counter = 0;

    /**
     * @param int $message_type
     * @param int $msgid
     * @return bool
     */
    public function isEmpty($message_type, $msgid=null)
    {
        if ($msgid) {
            return empty($this->command_awaits[$message_type][$msgid]);
        } else {
            return empty($this->command_awaits[$message_type]);
        }
    }

    /**
     * Add
     *
     * @param int   $message_type
     * @param int   $msgid
     * @param array $data
     */
    public function addWait($message_type, $msgid, array $data)
    {
        if (!isset($this->command_awaits[$message_type][$msgid])) {
            Debug::Log(Debug::DEBUG, "Waiting for " . Message::$name[$message_type] . " msgid={$msgid}");

            $this->command_awaits[$message_type][$msgid] = $data;
            ++ $this->command_awaits_counter;
        }
    }

    /**
     * Delete
     *
     * @param int $message_type
     * @param int $msgid
     */
    public function delWait($message_type, $msgid)
    {
        if (isset($this->command_awaits[$message_type][$msgid])) {
            Debug::Log(Debug::DEBUG, "Forget " . Message::$name[$message_type] . " msgid={$msgid}");

            unset($this->command_awaits[$message_type][$msgid]);
            -- $this->command_awaits_counter;
        }
    }

    /**
     * Get
     *
     * @param int $message_type
     * @param int $msgid
     * @return false|array
     */
    public function getWait($message_type, $msgid)
    {
        return $this->isEmpty($message_type, $msgid) ?
            false : $this->command_awaits[$message_type][$msgid];
    }

    /**
     * Get all by message_type
     *
     * @param int $message_type
     * @return array
     */
    public function getWaits($message_type)
    {
        return $this->isEmpty($this->command_awaits[$message_type]) ?
            false : $this->command_awaits[$message_type];
    }

    /**
     * Count
     *
     * @return int
     */
    public function countWaits()
    {
        return $this->command_awaits_counter;
    }
}

# EOF