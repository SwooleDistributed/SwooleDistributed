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
    public function __construct()
    {
        parent::__construct();
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