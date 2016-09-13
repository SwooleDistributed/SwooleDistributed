<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-9-1
 * Time: 下午4:25
 */

namespace Server\Client;

use Server\CoreBase\CoroutineNull;
use Server\CoreBase\ICoroutineBase;

class GetHttpClientCoroutine implements ICoroutineBase
{
    /**
     * @var Client
     */
    public $client;
    public $base_url;
    public $result;

    public function __construct($client, $base_url)
    {
        $this->result = CoroutineNull::getInstance();
        $this->base_url = $base_url;
        $this->client = $client;
        $this->send(function ($http_client) {
            $this->result = $http_client;
        });
    }

    public function send($callback)
    {
        $this->client->getHttpClient($this->base_url, $callback);
    }

    public function getResult()
    {
        return $this->result;
    }
}