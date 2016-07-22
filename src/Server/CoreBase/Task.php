<?php
namespace Server\CoreBase;
/**
 * Task 异步任务
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午12:00
 */
class Task extends TaskProxy
{
    /**
     * task只能使用同步redis
     * @var \Redis
     */
    public $redis;
    public function __construct()
    {
        parent::__construct();
        $this->redis = get_instance()->redis_client;
    }

    /**
     * sendToUid
     * @param $uid
     * @param $data
     */
    protected function sendToUid($uid,$data){
        $data = $this->pack->pack($data);
        get_instance()->sendToUid($uid, $data);
    }

    /**
     * sendToUids
     * @param $uids
     * @param $data
     */
    protected function sendToUids($uids,$data){
        $data = $this->pack->pack($data);
        get_instance()->sendToUids($uids, $data);
    }

    /**
     * sendToAll
     * @param $data
     */
    protected function sendToAll($data){
        $data = $this->pack->pack($data);
        get_instance()->sendToAll($data);
    }
}