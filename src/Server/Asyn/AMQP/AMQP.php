<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-6-26
 * Time: 下午5:38
 */

namespace Server\Asyn\AMQP;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;

class AMQP extends AbstractConnection
{
    /**
     * @param string $host
     * @param int $port
     * @param string $user
     * @param string $password
     * @param string $vhost
     * @param bool $insist
     * @param string $login_method
     * @param null $login_response
     * @param string $locale
     * @param int $connection_timeout
     * @param bool $keepalive
     * @param int $read_write_timeout
     * @param int $heartbeat
     * @throws \Exception
     */
    public function __construct(
        $host,
        $port,
        $user,
        $password,
        $vhost = '/',
        $insist = false,
        $login_method = 'AMQPLAIN',
        $login_response = null,
        $locale = 'en_US',
        $connection_timeout = 3,
        $keepalive = false,
        $read_write_timeout = -1,
        $heartbeat = 0
    )
    {
        $io = new SwooleIO($host, $port, $connection_timeout, $read_write_timeout, null, $keepalive, $heartbeat);

        parent::__construct(
            $user,
            $password,
            $vhost,
            $insist,
            $login_method,
            $login_response,
            $locale,
            $io,
            $heartbeat
        );
    }

    /**
     * @throws \Exception
     */
    public function waitAllChannel()
    {
        while (true) {
            foreach ($this->channels as $channel) {
                if ($channel instanceof AMQPNonBlockChannel) {
                    $channel->waitNonBlocking();
                }
            }
            try {
                list($frame_type, $frame_channel, $payload) = $this->wait_frame(0);
                if ($frame_channel === 0 && $frame_type === 8) {
                    // skip heartbeat frames and reduce the timeout by the time passed
                    $this->debug->debug_msg("received server heartbeat");
                } else {
                    // Not the channel we were looking for.  Queue this frame
                    //for later, when the other channel is looking for frames.
                    // Make sure the channel still exists, it could have been
                    // closed by a previous Exception.
                    if (isset($this->channels[$frame_channel])) {
                        array_push($this->channels[$frame_channel]->frame_queue, array($frame_type, $payload));
                    }
                }
            } catch (AMQPTimeoutException $e) {

            }
        }
    }

    /**
     * Fetches a channel object identified by the numeric channel_id, or
     * create that object if it doesn't already exist.
     *
     * @param int $channel_id
     * @return AMQPNonBlockChannel
     * @throws \Exception
     */
    public function channel($channel_id = null)
    {
        if (isset($this->channels[$channel_id])) {
            return $this->channels[$channel_id];
        }

        $channel_id = $channel_id ? $channel_id : $this->get_free_channel_id();
        $ch = new AMQPNonBlockChannel($this->connection, $channel_id);
        $this->channels[$channel_id] = $ch;

        return $ch;
    }
}