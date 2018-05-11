<?php

/**
 * MQTT Client
 */

namespace Server\Asyn\MQTT;

use Server\Asyn\MQTT\Message\Base;
use Server\Asyn\MQTT\Message\Will;

/**
 * Class MQTT
 *
 * @package Server\Asyn\MQTT
 */
class MQTT implements IMqtt
{
    /**
     * Client ID
     *
     * @var null|string
     */
    public $clientid;

    /**
     * Socket Connection
     *
     * @var SwooleClient
     */
    protected $socket;

    /**
     * Keep Alive Time
     * @var int
     */
    protected $keepalive = 60;

    /**
     * Connect Username
     *
     * @var string
     */
    protected $username = '';

    /**
     * Connect Password
     *
     * @var string
     */
    protected $password = '';

    /**
     * Connect Clean
     *
     * @var bool
     */
    protected $connect_clean = true;

    /**
     * Connect Will
     *
     * @var Will
     */
    protected $connect_will;

    /**
     * Version Code
     */
    const VERSION_3 = 3;
    const VERSION_3_0 = 3;
    const VERSION_3_1 = 3;
    const VERSION_3_1_1 = 4;
    /**
     * Current version
     *
     * Default: MQTT 3.0
     *
     * @var int
     */
    protected $version = self::VERSION_3_0;

    /**
     * Unix Timestamp
     *
     * @var int
     */
    protected $connected_time = 0;

    /**
     * @var CMDStore
     */
    protected $cmdstore = null;

    protected $keepalive_time = null;

    /**
     * Retry Timeout
     *
     * @var int
     */
    protected $retry_timeout = 5;

    /**
     * Constructor
     *
     * @param string $address
     * @param null|string $clientid
     * @param bool $check23
     */
    public function __construct($address, $clientid = null, $check23 = false)
    {
        # Create Socket Client Object
        $this->socket = new SwooleClient($address);
        # New Command Store
        $this->cmdstore = new CMDStore();

        # Check Client ID
        if($check23) {
            Utility::CheckClientID($clientid);
        }

        $this->clientid = $clientid;
    }

    /**
     * Retry Timeout for PUBLISH and Following Commands
     *
     * @param int $retry_timeout
     */
    public function setRetryTimeout($retry_timeout)
    {
        if ($retry_timeout > 1) {
            $this->retry_timeout = (int)$retry_timeout;
        }
    }

    /**
     * Create Message\Base object
     *
     * @param int $message_type
     * @return Message\Base
     * @throws Exception
     */
    public function getMessageObject($message_type)
    {
        return Message::Create($message_type, $this);
    }

    /**
     * Set Protocol Version
     *
     * @param string $version
     * @throws Exception
     */
    public function setVersion($version)
    {
        if ($version == self::VERSION_3 || $version == self::VERSION_3_1_1) {
            $this->version = $version;
        } else {
            throw new Exception('Invalid version');
        }
    }

    /**
     * Get Protocol Version
     *
     * @return string
     */
    public function version()
    {
        return $this->version;
    }

    /**
     * Set username/password
     *
     * @param string $username
     * @param string $password
     */
    public function setAuth($username = null, $password = null)
    {
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Set Keep Alive timer
     *
     * @param int $keepalive
     */
    public function setKeepalive($keepalive)
    {
        $this->keepalive = (int)$keepalive;
    }

    /**
     * Set Clean Session
     *
     * @param bool $clean
     */
    public function setConnectClean($clean)
    {
        $this->connect_clean = $clean ? true : false;
    }

    /**
     * Set Will Message
     *
     * @param string $topic
     * @param string $message
     * @param int $qos 0,1,2
     * @param int $retain bool
     */
    public function setWill($topic, $message, $qos = 0, $retain = 0)
    {
        $this->connect_will = new Will($topic, $message, $qos, $retain);
    }

    protected $call_handlers=[];

    /**
     * Set Message Handler
     *
     * @param $name
     * @param $call
     */
    public function on($name,$call)
    {
        $this->call_handlers[$name] = $call;
    }

    /**
     * Invoke Functions in Message Handler
     *
     * @param string $name
     * @param array $params
     */
    protected function call_handler($name, array $params = array())
    {
        if(array_key_exists($name,$this->call_handlers)) {
            go(function () use ($name, $params) {
                sd_call_user_func_array($this->call_handlers[$name], $params);
            });
        }
    }

    /**
     * Create Packet Identifier Generator
     *
     * @return PacketIdentifier
     */
    public function PIG()
    {
        $pi = new PacketIdentifier();

        # set msg id
        $pi->set(mt_rand(1, 65535));

        return $pi;
    }

    /**
     * Connect to broker
     * s*
     * @return bool
     * @throws Exception
     */
    public function connect()
    {
        /*
         The Server MUST process a second CONNECT Packet sent from a Client as a protocol violation
         and disconnect the Client [MQTT-3.1.0-2]

         So CONNECT SHOULD be a blocking connection.
         */
        $this->socket->connect(function ($cli) {
            Debug::Log(Debug::INFO, 'connect(): Connection established.');
            /**
             * @var Message\CONNECT $connectobj
             */
            $connectobj = $this->getMessageObject(Message::CONNECT);

            if (!$this->connect_clean && empty($this->clientid)) {
                throw new Exception('Client id must be provided if Clean Session flag is set false.');
            }

            # default client id
            if (empty($this->clientid)) {
                $this->clientid = 'mqtt' . substr(md5(uniqid('mqtt', true)), 8, 16);
            } else {
                $connectobj->setClientId($this->clientid);
            }
            Debug::Log(Debug::DEBUG, 'connect(): clientid=' . $this->clientid);

            $connectobj->setKeepalive($this->keepalive);
            Debug::Log(Debug::DEBUG, 'connect(): keepalive=' . $this->keepalive);

            $connectobj->setAuth($this->username, $this->password);
            Debug::Log(Debug::DEBUG, 'connect(): username=' . $this->username . ' password=' . $this->password);

            $connectobj->setClean($this->connect_clean);

            if ($this->connect_will instanceof Will) {
                $connectobj->setWill($this->connect_will);
            }

            $length = 0;

            $bytes_written = $this->message_write($connectobj, $length);
            Debug::Log(Debug::DEBUG, 'connect(): bytes written=' . $bytes_written);

            $this->keepalive_time = swoole_timer_tick($this->keepalive * 500, [$this, 'keepalive']);
        }, [$this, 'onReceive'],function (){
            secho("MQTT", "连接mqtt服务器失败,正在重试");
            swoole_timer_after(1000,[$this,'connect']);
        },function (){
            if ($this->keepalive_time!=null) {
                swoole_timer_clear($this->keepalive_time);
                $this->keepalive_time = null;
            }
            $this->connect();
        });
    }

    /**
     * 接受
     * @param $cli
     * @param $data
     */
    public function onReceive($cli, $data)
    {
        $message_object = $this->message_read($data);
        switch ($message_object->getMessageType()) {
            case Message::CONNACK:
                Debug::Log(Debug::INFO, 'connect(): connected=1');
                # save current time for ping
                $this->connected_time = time();
                # Call connect
                $this->call_handler('connack', array($this, $message_object));
                break;
            case Message::PINGRESP:
                array_shift($this->ping_queue);
                Debug::Log(Debug::INFO, 'loop(): received PINGRESP');

                $this->last_ping_time = time();

                break;

            # Process PUBLISH
            # in: Client <- Server, Step 1
            case Message::PUBLISH:
                /**
                 * @var Message\PUBLISH $message_object
                 */

                Debug::Log(Debug::INFO, 'loop(): received PUBLISH');

                $qos = $message_object->getQoS();

                $msgid = $message_object->getMsgID();

                if ($qos == 0) {
                    Debug::Log(Debug::DEBUG, 'loop(): PUBLISH QoS=0 PASS');
                    # call handler
                    //0直接执行
                    $this->call_handler('publish', array($this, $message_object));
                    # Do nothing
                } else if ($qos == 1) {
                    # call handler
                    //1先执行再回复，可以结合飞行窗口实现顺序发送
                    $this->call_handler('publish', array($this, $message_object));
                    # PUBACK
                    $puback_bytes_written = $this->simpleCommand(Message::PUBACK, $msgid);
                    Debug::Log(Debug::DEBUG, 'loop(): PUBLISH QoS=1 PUBACK written=' . $puback_bytes_written);

                } else if ($qos == 2) {
                    # PUBREC
                    $pubrec_bytes_written = $this->simpleCommand(Message::PUBREC, $msgid);
                    Debug::Log(Debug::DEBUG, 'loop(): PUBLISH QoS=2 PUBREC written=' . $pubrec_bytes_written);
                    $this->cmdstore->addWait(
                        Message::PUBREL,
                        $msgid,
                        array(
                            'msgid' => $msgid,
                            'retry_after' => time() + $this->retry_timeout,
                        )
                    );
                    # call handler
                    //2先回复收到再执行
                    $this->call_handler('publish', array($this, $message_object));
                } else {
                    # wrong packet
                    Debug::Log(Debug::WARN, 'loop(): PUBLISH Invalid QoS');
                }
                break;

            # Process PUBACK
            # in: Client -> Server, QoS = 1, Step 2
            case Message::PUBACK:

                /**
                 * @var Message\PUBACK $message_object
                 */

                # Message has been published (QoS 1)

                $msgid = $message_object->getMsgID();
                Debug::Log(Debug::INFO, 'loop(): received PUBACK msgid=' . $msgid);
                # Verify Packet Identifier
                $this->call_handler('puback', array($this, $message_object));

                $this->cmdstore->delWait(Message::PUBACK, $msgid);
                break;

            # Process PUBREC, send PUBREL
            # in: Client -> Server, QoS = 2, Step 2
            case Message::PUBREC:
                /**
                 * @var Message\PUBREC $message_object
                 */

                $msgid = $message_object->getMsgID();
                Debug::Log(Debug::INFO, 'loop(): received PUBREC msgid=' . $msgid);

                $this->cmdstore->delWait(Message::PUBREC, $msgid);

                # PUBREL
                Debug::Log(Debug::INFO, 'loop(): send PUBREL msgid=' . $msgid);
                $pubrel_bytes_written = $this->simpleCommand(Message::PUBREL, $msgid);


                $this->cmdstore->addWait(
                    Message::PUBCOMP,
                    $msgid,
                    array(
                        'msgid' => $msgid,
                        'retry_after' => time() + $this->retry_timeout,
                    )
                );

                Debug::Log(Debug::DEBUG, 'loop(): PUBREL QoS=2 PUBREL written=' . $pubrel_bytes_written);

                $this->call_handler('pubrec', array($this, $message_object));
                break;


            # Process PUBREL
            # in: Client <- Server, QoS = 2, Step 3
            case Message::PUBREL:
                /**
                 * @var Message\PUBREL $message_object
                 */

                $msgid = $message_object->getMsgID();
                Debug::Log(Debug::INFO, 'loop(): received PUBREL msgid=' . $msgid);

                $this->cmdstore->delWait(Message::PUBREL, $msgid);

                # PUBCOMP
                Debug::Log(Debug::INFO, 'loop(): send PUBCOMP msgid=' . $msgid);
                $pubcomp_bytes_written = $this->simpleCommand(Message::PUBCOMP, $msgid);

                Debug::Log(Debug::DEBUG, 'loop(): PUBREL QoS=2 PUBCOMP written=' . $pubcomp_bytes_written);

                $this->call_handler('pubrel', array($this, $message_object));
                break;

            # Process PUBCOMP
            # in: Client -> Server, QoS = 2, Step 4
            case Message::PUBCOMP:

                # Message has been published (QoS 2)

                /**
                 * @var Message\PUBCOMP $message_object
                 */

                $msgid = $message_object->getMsgID();
                Debug::Log(Debug::INFO, 'loop(): received PUBCOMP msgid=' . $msgid);

                $this->cmdstore->delWait(Message::PUBCOMP, $msgid);

                $this->call_handler('pubcomp', array($this, $message_object));
                break;

            # Process SUBACK
            case Message::SUBACK:

                /**
                 * @var Message\SUBACK $message_object
                 */

                $return_codes = $message_object->getReturnCodes();
                $msgid = $message_object->getMsgID();
                Debug::Log(Debug::INFO, 'loop(): received SUBACK msgid=' . $msgid);

                if (!isset($this->subscribe_awaits[$msgid])) {
                    Debug::Log(Debug::WARN, 'loop(): SUBACK Message identifier not found: ' . $msgid);
                } else {
                    if (count($this->subscribe_awaits[$msgid]) != count($return_codes)) {
                        Debug::Log(Debug::WARN, 'loop(): SUBACK returned qos list doesn\'t match SUBSCRIBE');
                    } else {
                        # save max_qos list from suback
                        foreach ($return_codes as $k => $tqos) {
                            if ($return_codes != 0x80) {
                                $this->topics[$this->subscribe_awaits[$msgid][$k][0]] = $tqos;
                            } else {
                                Debug::Log(
                                    Debug::WARN,
                                    "loop(): Failed to subscribe '{$this->subscribe_awaits[$msgid][$k][0]}'. Request QoS={$this->subscribe_awaits[$msgid][$k][1]}"
                                );
                            }
                        }
                    }
                }

                $this->call_handler('suback', array($this, $message_object));

                break;

            # Process UNSUBACK
            case Message::UNSUBACK:
                /**
                 * @var Message\UNSUBACK $message_object
                 */

                $msgid = $message_object->getMsgID();
                Debug::Log(Debug::INFO, 'loop(): received UNSUBACK msgid=' . $msgid);

                if (!isset($this->unsubscribe_awaits[$msgid])) {
                    Debug::Log(Debug::WARN, 'loop(): UNSUBACK Message identifier not found ' . $msgid);
                } else {
                    foreach ($this->unsubscribe_awaits[$msgid] as $topic) {
                        Debug::Log(Debug::WARN, "loop(): Unsubscribe topic='{$topic}'");
                        unset($this->topics[$topic]);
                    }
                }

                $this->call_handler('unsuback', array($this, $message_object));
                break;

            default:
                return;
        }
    }

    /**
     * Disconnect connection
     *
     * @return bool
     */
    public function disconnect()
    {
        Debug::Log(Debug::INFO, 'disconnect()');

        $this->simpleCommand(Message::DISCONNECT);

        /*
         After sending a DISCONNECT Packet the Client:
         MUST close the Network Connection [MQTT-3.14.4-1].
         MUST NOT send any more Control Packets on that Network Connection [MQTT-3.14.4-2].
         */
        $this->socket->close();
        $this->call_handler('disconnect', array($this));

        return true;
    }

    /**
     * Reconnect connection
     *
     * @param bool $close_current close current existed connection
     * @return bool
     */
    public function reconnect($close_current = true)
    {
        Debug::Log(Debug::INFO, 'reconnect()');
        if ($close_current) {
            Debug::Log(Debug::DEBUG, 'reconnect(): close current');
            if($this->socket->isConnected()) {
                $this->disconnect();
            }
            $this->socket->close();
        }

        $this->connect();
    }

    /**
     * Publish Message to topic
     *
     * @param string $topic
     * @param string $message
     * @param int $qos
     * @param int $retain
     * @return array|bool
     * @throws Exception
     */
    public function publish($topic, $message, $qos = 0, $retain = 0, &$msgid = null)
    {
        # set dup 0
        $dup = 0;

        # initial msgid = 0
        $msgid = 0;

        return $this->do_publish($topic, $message, $qos, $retain, $msgid, $dup);
    }

    /**
     * Publish Message to topic
     *
     * @param string $topic
     * @param string $message
     * @param int $qos Optional, QoS, Default to 0
     * @param int $retain Optional, RETAIN, Default to 0
     * @param int|null & $msgid Optional, Packet Identifier
     * @param int $dup Optional, Default to 0
     * @return array|bool
     */
    public function do_publish($topic, $message, $qos = 0, $retain = 0, & $msgid = 0, $dup = 0)
    {
        /**
         * @var PacketIdentifier[] $pis
         */
        static $pis = array();

        if ($qos) {
            if (!isset($pis[$qos])) {
                $pis[$qos] = $this->PIG();
            }

            if (!$msgid) {
                $msgid = $pis[$qos]->next();
            }
        }

        Debug::Log(Debug::INFO, "publish() QoS={$qos}, MsgID={$msgid}, DUP={$dup}");
        /**
         * @var Message\PUBLISH $publishobj
         */
        $publishobj = $this->getMessageObject(Message::PUBLISH);

        $publishobj->setTopic($topic);
        $publishobj->setMessage($message);

        $publishobj->setDup($dup);
        $publishobj->setQos($qos);
        $publishobj->setRetain($retain);

        $publishobj->setMsgID($msgid);

        $publish_bytes_written = $this->message_write($publishobj);
        Debug::Log(Debug::DEBUG, 'do_publish(): bytes written=' . $publish_bytes_written);

        if ($qos == 1) {
            # QoS = 1, PUBLISH + PUBACK
            if (!$dup) {
                $this->cmdstore->addWait(
                    Message::PUBACK,
                    $msgid,
                    array(
                        'msgid' => $msgid,
                        'retry' => array(
                            'retain' => $retain,
                            'topic' => $topic,
                            'message' => $message,
                        ),
                        'retry_after' => time() + $this->retry_timeout,
                    )
                );
            }
        } else if ($qos == 2) {
            # QoS = 2, PUBLISH + PUBREC + PUBREL + PUBCOMP
            if (!$dup) {
                $this->cmdstore->addWait(
                    Message::PUBREC,
                    $msgid,
                    array(
                        'msgid' => $msgid,
                        'retry' => array(
                            'retain' => $retain,
                            'topic' => $topic,
                            'message' => $message,
                        ),
                        'retry_after' => time() + $this->retry_timeout,
                    )
                );
            }
        }

        return array(
            'qos' => $qos,
            'ret' => $publish_bytes_written != false,
            'publish' => $publish_bytes_written,
            'msgid' => $msgid,
        );
    }

    /**
     * Currently Subscribed Topics (Topic Filter)
     *
     * @var array
     */
    protected $topics = array();

    /**
     * Topics to Subscribe (Topic Filter)
     *
     * @var array
     */
    protected $topics_to_subscribe = array();

    /**
     * Topics to Unsubscribe (Topic Filter)
     *
     * @var array
     */
    protected $topics_to_unsubscribe = array();

    /**
     * SUBSCRIBE
     *
     * @param array $topics array($topic_filter => $topic_qos)
     * @return bool
     */
    public function subscribe(array $topics)
    {
        foreach ($topics as $topic_filter => $topic_qos) {
            $this->topics_to_subscribe[$topic_filter] = $topic_qos;
        }
        list($last_subscribe_msgid, $last_subscribe_topics) = $this->do_subscribe();
        $this->subscribe_awaits[$last_subscribe_msgid] = $last_subscribe_topics;
    }

    /**
     * UNSUBSCRIBE
     *
     * @param array $topics Topic Filters
     * @return bool
     * @throws Exception
     */
    public function unsubscribe(array $topics)
    {
        foreach ($topics as $topic_filter) {
            $this->topics_to_unsubscribe[] = $topic_filter;
        }
        list($last_unsubscribe_msgid, $last_unsubscribe_topics) = $this->do_unsubscribe();
        $this->unsubscribe_awaits[$last_unsubscribe_msgid] = $last_unsubscribe_topics;
    }

    /**
     * DO SUBSCRIBE
     *
     * @return array (msgid, topic qos)
     * @throws Exception
     */
    protected function do_subscribe()
    {
        /**
         * Packet Identifier Generator
         *
         * @var PacketIdentifier $pi
         */
        static $pi = null;
        if (!$pi) {
            $pi = $this->PIG();
        }

        $msgid = $pi->next();

        # send SUBSCRIBE

        /**
         * @var Message\SUBSCRIBE $subscribeobj
         */
        $subscribeobj = $this->getMessageObject(Message::SUBSCRIBE);
        $subscribeobj->setMsgID($msgid);

        $all_topic_qos = array();
        foreach ($this->topics_to_subscribe as $topic_filter => $topic_qos) {
            $subscribeobj->addTopic(
                $topic_filter,
                $topic_qos
            );

            $all_topic_qos[] = array($topic_filter, $topic_qos);
            unset($this->topics_to_subscribe[$topic_filter]);
        }

        Debug::Log(Debug::DEBUG, 'do_subscribe(): msgid=' . $msgid);
        $subscribe_bytes_written = $this->message_write($subscribeobj);
        Debug::Log(Debug::DEBUG, 'do_subscribe(): bytes written=' . $subscribe_bytes_written);

        # The Server is permitted to start sending PUBLISH packets matching the Subscription before the Server sends the SUBACK Packet.
        # No SUBACK processing here, go to loop()

        return array($msgid, $all_topic_qos);
    }

    /**
     * DO Unsubscribe topics
     *
     * @return int
     * @throws Exception
     */
    protected function do_unsubscribe()
    {
        /**
         * Packet Identifier Generator
         *
         * @var PacketIdentifier $pi
         */
        static $pi = null;
        if (!$pi) {
            $pi = $this->PIG();
        }

        $msgid = $pi->next();

        # send SUBSCRIBE
        /**
         * @var Message\UNSUBSCRIBE $unsubscribeobj
         */
        $unsubscribeobj = $this->getMessageObject(Message::UNSUBSCRIBE);
        $unsubscribeobj->setMsgID($msgid);

        $unsubscribe_topics = array();
        # no need to check if topic is subscribed before unsubscribing
        foreach ($this->topics_to_unsubscribe as $tn => $topic_filter) {
            $unsubscribeobj->addTopic($topic_filter);
            unset($this->topics_to_unsubscribe[$tn]);
            $unsubscribe_topics[] = $topic_filter;
        }

        $unsubscribe_bytes_written = $this->message_write($unsubscribeobj);

        Debug::Log(Debug::DEBUG, 'unsubscribe(): bytes written=' . $unsubscribe_bytes_written);

        return array($msgid, $unsubscribe_topics);
    }

    /**
     * @var array
     */
    protected $subscribe_awaits = array();
    /**
     * @var array
     */
    protected $unsubscribe_awaits = array();

    protected $last_ping_time = 0;

    /**
     * Keep Alive
     *
     * If the Keep Alive value is non-zero and the Server does not receive a Control Packet from the Client
     * within one and a half times the Keep Alive time period, it MUST disconnect the Network Connection to
     * the Client as if the network had failed [MQTT-3.1.2-24].
     *
     * @return bool
     */
    public function keepalive()
    {
        $current_time = time();

        if (empty($this->last_ping_time)) {
            if ($this->connected_time) {
                $this->last_ping_time = $this->connected_time;
            } else {
                $this->last_ping_time = $current_time;
            }
        }

        Debug::Log(Debug::DEBUG, "keepalive(): current_time={$current_time}, last_ping_time={$this->last_ping_time}, keepalive={$this->keepalive}");
        $this->ping();
    }

    protected $ping_queue = array();
    /**
     * Send PINGREQ
     *
     * @return bool
     */
    public function ping()
    {
        Debug::Log(Debug::INFO, 'ping()');
        $this->simpleCommand(Message::PINGREQ);

        $this->ping_queue[] = time();

        return count($this->ping_queue);
    }

    /**
     * Send Simple Commands
     *
     *
     * @param int $type
     * @param int $msgid
     * @return int           bytes written
     */
    protected function simpleCommand($type, $msgid = 0)
    {
        $msgobj = $this->getMessageObject($type);

        if ($msgid) {
            $msgobj->setMsgID($msgid);
        }

        return $this->message_write($msgobj);
    }

    /**
     * Write Message Object
     *
     * @param Message\Base $object
     * @param int & $length
     * @return int
     * @throws Exception
     */
    protected function message_write(Base $object, & $length = 0)
    {
        Debug::Log(Debug::DEBUG, 'Message write: message_type=' . Message::$name[$object->getMessageType()]);
        $length = 0;
        $message = $object->build($length);
        $bytes_written = $this->socket->send($message, $length);
        return $bytes_written;
    }

    /**
     * Read Message And Create Message Object
     *
     * @return \Server\Asyn\MQTT\Message\Base
     * @throws \Server\Asyn\MQTT\Exception
     */
    protected function message_read($data)
    {
        $cmd = Utility::ParseCommand(ord($data[0]));
        $message_type = $cmd['message_type'];
        $flags = $cmd['flags'];
        Debug::Log(Debug::DEBUG, "message_read(): message_type=" . Message::$name[$message_type] . ", flags={$flags}");
        Debug::Log(Debug::DEBUG, 'message_read(): Dump', $data);
        $pos = 1;
        $remaining_length = Utility::DecodeLength($data, $pos);
        $message_object = $this->getMessageObject($message_type);
        $message_object->decode($data, $remaining_length);
        return $message_object;
    }
}
