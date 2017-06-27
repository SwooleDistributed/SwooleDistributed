<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-6-26
 * Time: 下午5:38
 */

namespace Server\Asyn\AMQP;

use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Wire\IO\StreamIO;

class AMQP extends AbstractConnection
{
    /**
     * @param string $host
     * @param string $port
     * @param string $user
     * @param string $password
     * @param string $vhost
     * @param bool $insist
     * @param string $login_method
     * @param null $login_response
     * @param string $locale
     * @param float $connection_timeout
     * @param float $read_write_timeout
     * @param null $context
     * @param bool $keepalive
     * @param int $heartbeat
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
        $connection_timeout = 3.0,
        $read_write_timeout = 3.0,
        $context = null,
        $keepalive = false,
        $heartbeat = 0
    ) {
        $io = new StreamIO(
            $host,
            $port,
            $connection_timeout,
            $read_write_timeout,
            $context,
            $keepalive,
            $heartbeat
        );

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

    public function channel($channel_id = null)
    {
        $channel =  parent::channel($channel_id);
        swoole_event_add($this->getSocket(),function ()use(&$channel){
            $channel->wait(null,true);
        },null,SWOOLE_EVENT_READ);
        return $channel;
    }
}