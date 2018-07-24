<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/5/10
 * Time: 14:54
 */

namespace Server\Components\Backstage;


use Server\CoreBase\SwooleException;
use Server\Pack\ConsolePack;

class ChannelMonitorClient
{
    protected $client;

    /**
     * ChannelMonitorClient constructor.
     * @param $ip
     * @param $port
     * @param $uid
     * @param $filters
     * @throws \Exception
     */
    public function __construct($ip, $port, $uid, $filters = null)
    {
        $this->client = new \Co\http\Client($ip, $port);
        $this->client->set(['timeout' => -1]);
        $result = $this->client->upgrade("/?type=channel&uid=$uid");
        if (!$result) {
            throw new \Exception("连接不上服务器");
        }
        $pack = new ConsolePack();
        while (true) {
            $data = $this->client->recv();
            try {
                $data = $pack->unPack($data->data);
                $topic = $data->topic;
                $playlod = json_encode($data->playlod, JSON_UNESCAPED_UNICODE);
                $playlod = json_decode($playlod, true);
                if (!empty($filters)) {
                    foreach ($filters as $filter) {
                        list($key, $value) = explode(":", $filter);
                        if ($this->findKey($key, $playlod) == $value) {
                            echo $topic . "\n";
                            print_r($playlod);
                        }
                    }
                } else {
                    echo $topic . "\n";
                    print_r($playlod);
                }
            } catch (SwooleException $e) {
            }
        }
    }

    public function findKey($find_key, $map)
    {
        foreach ($map as $key => $value) {
            if ($key == $find_key && !is_array($value)) {
                return $value;
            }
            if (is_array($value)) {
                $result = $this->findKey($find_key, $value);
                if (!empty($result)) {
                    return $result;
                }
            }
        }
    }
}