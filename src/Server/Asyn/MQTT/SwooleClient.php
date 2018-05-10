<?php

/**
 * MQTT Client
 */


namespace Server\Asyn\MQTT;

/**
 * Swoole Client
 *
 * @package Server\Asyn\MQTT
 */
class SwooleClient
{
    /**
     * swoole Connection Resource
     *
     * @var resource
     */
    protected $client;
    /**
     * Server Address
     *
     * @var string
     */
    protected $address;


    public function __construct($address)
    {
        $this->address = $address;
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Get Server Address
     *
     * @return string
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * create socket
     * @param $onConnect
     * @param $onReceive
     * @param $onError
     * @param $onClose
     * @return void
     */
    public function connect($onConnect,$onReceive,$onError,$onClose)
    {
        Debug::Log(Debug::DEBUG, 'swoole_connect(): connect to='.$this->address);
        $this->client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);
        $this->client->set([
            'open_mqtt_protocol'     => true,
            'package_max_length'    => 2000000,  //协议最大长度
        ]);
        $this->client->on("connect", $onConnect);
        $this->client->on("receive", $onReceive);
        $this->client->on("error", $onError);
        $this->client->on("close", $onClose);
        $arr = parse_url($this->address);
        swoole_async_dns_lookup($arr['host'], function($host, $ip) use($arr){
            $this->client->connect($ip, $arr['port']);
        });

    }

    /**
     * @param $packet
     * @param $packet_size
     * @return mixed
     */
    public function send($packet, $packet_size)
    {
        Debug::Log(Debug::DEBUG, "socket_write(length={$packet_size})", $packet);
        $this->client->send($packet);
        return $packet_size;
    }

    /**
     * Close socket
     *
     * @return bool
     */
    public function close()
    {
        if($this->isConnected()) {
            $this->client->close();
        }
    }

    /**
     * @return bool
     */
    public function isConnected()
    {
        return $this->client->isConnected();
    }
}
