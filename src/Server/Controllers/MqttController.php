<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-31
 * Time: 下午3:40
 */

namespace Server\Controllers;


use Server\Asyn\MQTT\Message\CONNACK;
use Server\Asyn\MQTT\Message\CONNECT;
use Server\Asyn\MQTT\Message\DISCONNECT;
use Server\Asyn\MQTT\Message\PINGREQ;
use Server\Asyn\MQTT\Message\PINGRESP;
use Server\Asyn\MQTT\Message\PUBACK;
use Server\Asyn\MQTT\Message\PUBCOMP;
use Server\Asyn\MQTT\Message\PUBLISH;
use Server\Asyn\MQTT\Message\PUBREC;
use Server\Asyn\MQTT\Message\PUBREL;
use Server\Asyn\MQTT\Message\SUBACK;
use Server\Asyn\MQTT\Message\SUBSCRIBE;
use Server\Asyn\MQTT\Message\UNSUBACK;
use Server\Asyn\MQTT\Message\UNSUBSCRIBE;
use Server\CoreBase\Controller;
use Server\Pack\MqttPack;

class MqttController extends Controller
{
    /**
     * @var MqttPack
     */
    public $pack;

    public function initialization($controller_name, $method_name)
    {
        parent::initialization($controller_name, $method_name);
        $this->pack = get_instance()->portManager->getPackFromFd($this->fd);
    }

    /**
     * @param CONNECT $connect
     */
    public function connect(CONNECT $connect)
    {
        $connack = new CONNACK($this->pack);
        if ($connect->getUserNameFlag()) {
            list($auth, $uid) = $this->auth($connect->username, $connect->password);
            if ($auth) {
                $this->bindUid($uid);
                $connack->setReturnCode(0);
                $connack->setSessionPresent(0);
                $this->send($connack);
            } else {
                $connack->setReturnCode(0x04);
                $connack->setSessionPresent(0);
                $this->send($connack);
            }
        } else {
            $connack->setReturnCode(0x05);
            $connack->setSessionPresent(0);
            $this->send($connack);
        }
    }

    /**
     * @param PUBLISH $publish
     */
    public function publish(PUBLISH $publish)
    {
        $qos = $publish->getQos();
        $topic = $publish->getTopic();
        $data = $publish->getMessage();
        $msgId = $publish->getMsgID();
        $this->sendPub($topic, $data, false);
        switch ($qos) {
            case 1:
                $puback = new PUBACK($this->pack);
                $puback->setMsgID($msgId);
                $this->send($puback, false);
                break;
            case 2:
                $pubrec = new PUBREC($this->pack);
                $pubrec->setMsgID($msgId);
                $this->send($pubrec, false);
                break;
        }
    }

    /**
     * @param PUBREL $pubrel
     */
    public function pubrel(PUBREL $pubrel)
    {
        $msgId = $pubrel->getMsgID();
        $pubcomp = new PUBCOMP($this->pack);
        $pubcomp->setMsgID($msgId);
        $this->send($pubcomp);
    }


    public function subscribe(SUBSCRIBE $subscribe)
    {
        $topics = $subscribe->getTopic();
        $codes = [];
        foreach ($topics as $topic => $qos) {
            $codes[] = $qos;
            $this->addSub($topic);
        }
        $suback = new SUBACK($this->pack);
        $suback->setMsgID($subscribe->getMsgID());
        $suback->setReturnCodes($codes);
        $this->send($suback);
    }

    public function unsubscribe(UNSUBSCRIBE $unsubscribe)
    {
        $topics = $unsubscribe->getTopic();
        foreach ($topics as $topic) {
            $this->removeSub($topic);
        }
        $unsuback = new UNSUBACK($this->pack);
        $unsuback->setMsgID($unsubscribe->getMsgID());
        $this->send($unsuback);
    }

    public function pingreq(PINGREQ $pingreq)
    {
        $pingresp = new PINGRESP($this->pack);
        $this->send($pingresp);
    }

    public function disconnect(DISCONNECT $disconnect)
    {
        $this->close();
    }

    /**
     * 进行验证
     * @param $username
     * @param $password
     * @return array
     */
    protected function auth($username, $password)
    {
        $uid = $this->fd;
        return [true, $uid];
    }
}