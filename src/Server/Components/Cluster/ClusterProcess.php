<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-15
 * Time: 上午10:52
 */

namespace Server\Components\Cluster;

use Ds\Set;
use Server\Asyn\HttpClient\HttpClient;
use Server\Components\Event\EventDispatcher;
use Server\Components\Process\Process;
use Server\CoreBase\Actor;
use Server\Start;

class ClusterProcess extends Process
{
    const TYPE_UID = "type_uid";
    const TYPE_ACTOR = "type_actor";

    protected $map = [];
    protected $actorMap = [];
    protected $client = [];
    protected $node_name;
    protected $port;
    protected $subArr = [];
    protected $node_index = 0;
    /**
     * @var HttpClient
     */
    protected $consul;

    /**
     * @param $process
     * @throws \Exception
     */
    public function start($process)
    {
        $this->node_name = getNodeName();
        $this->actorMap[$this->node_name] = new Set();
        //通知所有Actor恢复注册
        Actor::call(Actor::ALL_COMMAND, 'recoveryRegister', null, true);
        if (get_instance()->isCluster()) {
            $this->map[$this->node_name] = new Set();
            foreach (get_instance()->server->connections as $fd) {
                $fdinfo = get_instance()->server->connection_info($fd);
                $uid = $fdinfo['uid'] ?? null;
                if ($uid != null) {
                    $this->map[$this->node_name]->add($uid);
                }
            }
            $this->consul = new HttpClient(null, 'http://127.0.0.1:8500');
            $this->port = $this->config['cluster']['port'];
            swoole_timer_after(2000, function () {
                $this->updateFromConsul();
            });
        }
    }

    /**
     * 返回node的数量
     * @return array
     */
    public function getNodeCountAndIndex()
    {
        return [count($this->client)+1,$this->node_index];
    }
    /**
     *恢复注册Actor
     * @param $actor
     */
    public function recoveryRegisterActor($actor)
    {
        $this->th_addActor($this->node_name, $actor);
    }

    /**
     * 添加Actor
     * @param $actor
     * @return bool
     */
    public function my_addActor($actor)
    {
        $node = $this->searchActor($actor);
        if ($node) return false;
        $this->th_addActor($this->node_name, $actor);
        foreach ($this->client as $client) {
            $client->addNodeActor($this->node_name, $actor);
        }
        return true;
    }

    public function th_addActor($node_name, $actor)
    {
        if (!isset($this->actorMap[$node_name])) {
            $this->actorMap[$node_name] = new Set();
        }
        $this->actorMap[$node_name]->add($actor);
    }

    /**
     * 移除Actor
     * @param $actor
     */
    public function my_removeActor($actor)
    {
        $this->actorMap[$this->node_name]->remove($actor);
        foreach ($this->client as $client) {
            $client->removeNodeActor($this->node_name, $actor);
        }
    }

    public function th_removeActor($node_name, $actor)
    {
        if (isset($this->actorMap[$node_name])) {
            $this->actorMap[$node_name]->remove($actor);
        }
    }

    /**
     * 与Actor通讯
     * @param $actor
     * @param $data
     * @throws \Exception
     */
    public function callActor($actor, $data)
    {
        $node_name = $this->searchActor($actor);
        if ($node_name) {
            if ($node_name == $this->node_name) {//本机
                EventDispatcher::getInstance()->dispatch(Actor::SAVE_NAME . $actor, $data, false, true);
                return;
            }
            if (!isset($this->client[$node_name])) return;
            $this->client[$node_name]->callActor($actor, $data);
        }
    }

    /**
     * 通讯的返回值
     * @param $workerId
     * @param $token
     * @param $result
     * @param $node_name
     */
    public function callActorBack($workerId, $token, $result, $node_name)
    {
        if ($node_name) {
            if ($node_name == $this->node_name) {//本机
                EventDispatcher::getInstance()->dispathToWorkerId($workerId, $token, $result);
                return;
            }
            if (!isset($this->client[$node_name])) return;
            $this->client[$node_name]->callActorBack($workerId, $token, $result);
        }
    }

    /**
     * 查找Actor在哪个node
     * @param $actor
     * @return bool|int|string
     */
    public function searchActor($actor)
    {
        if (empty($actor)) return false;
        foreach ($this->actorMap as $node_name => $set) {
            if ($set->contains($actor)) {
                return $node_name;
            }
        }
        return false;
    }

    /**
     * 自身增加了一个uid
     * @param $uid
     * @throws \Server\Asyn\MQTT\Exception
     */
    public function my_addUid($uid)
    {
        $this->map[$this->node_name]->add($uid);
        foreach ($this->client as $client) {
            $client->addNodeUid($this->node_name, $uid);
        }
        if (Start::isLeader()) {
            get_instance()->pub('$SYS/uidcount', $this->countOnline());
        }
    }

    /**
     * 自身减少了一个uid
     * @param $uid
     * @throws \Server\Asyn\MQTT\Exception
     */
    public function my_removeUid($uid)
    {
        if (get_instance()->isCluster()) {
            $this->map[$this->node_name]->remove($uid);
            foreach ($this->client as $client) {
                $client->removeNodeUid($this->node_name, $uid);
            }
            if (Start::isLeader()) {
                get_instance()->pub('$SYS/uidcount', $this->countOnline());
            }
        }
        $this->my_clearUidSub($uid);
    }

    /**
     * 踢人
     * @param $uid
     */
    public function my_kickUid($uid)
    {
        if (empty($uid)) return;
        $node_name = $this->searchUid($uid);
        if ($node_name) {
            if (!isset($this->client[$node_name])) return;
            $this->client[$node_name]->kickUid($uid);
        }
    }

    /**
     * @param $uid
     * @param $data
     */
    public function my_sendToUid($uid, $data)
    {
        if (empty($uid)) return;
        $node_name = $this->searchUid($uid);
        if ($node_name) {
            if (!isset($this->client[$node_name])) return;
            $this->client[$node_name]->sendToUid($uid, $data);
        }
    }

    /**
     * @param $uids
     * @param $data
     */
    public function my_sendToUids($uids, $data)
    {
        foreach ($this->map as $node_name => $array) {
            $guids = [];
            foreach ($uids as $uid) {
                if ($array->contains($uid)) {
                    $guids[] = $uid;
                }
            }
            if (count($guids) > 0) {
                $this->client[$node_name]->sendToUids($guids, $data);
            }
        }
    }

    /**
     * @param $data
     */
    public function my_sendToAll($data)
    {
        foreach ($this->client as $client) {
            $client->sendToAll($data);
        }
    }

    public function my_sendToAllFd($data)
    {
        foreach ($this->client as $client) {
            $client->sendToAllFd($data);
        }
    }

    /**
     * 添加订阅
     * @param $topic
     * @param $uid
     */
    public function my_addSub($topic, $uid)
    {
        if (empty($uid)) return;
        if (!isset($this->subArr[$topic])) {
            $this->subArr[$topic] = new Set();
        }
        $this->subArr[$topic]->add($uid);
    }

    /**
     * 移除订阅
     * @param $topic
     * @param $uid
     */
    public function my_removeSub($topic, $uid)
    {
        if (empty($uid)) return;
        if (isset($this->subArr[$topic])) {
            $this->subArr[$topic]->remove($uid);
            if ($this->subArr[$topic]->count() == 0) {
                unset($this->subArr[$topic]);
            }
        }
    }

    /**
     * 发布订阅
     * @param $topic
     * @param $data
     * @param array $excludeUids
     */
    public function my_pub($topic, $data, $excludeUids = [])
    {
        $this->th_pub($topic, $data, $excludeUids);
        foreach ($this->client as $client) {
            $client->pub($topic, $data, $excludeUids);
        }
    }

    /**
     * event派发
     * @param $type
     * @param $data
     */
    public function my_dispatchEvent($type, $data)
    {
        foreach ($this->client as $client) {
            $client->dispatchEvent($type, $data);
        }
    }

    /**
     * 构建订阅树,只允许5层
     * @param $topic
     * @return Set
     */
    protected function buildTrees($topic)
    {
        $isSYS = false;
        if ($topic[0] == "$") {
            $isSYS = true;
        }
        $p = explode("/", $topic);
        $countPlies = count($p);
        $result = new Set();
        if (!$isSYS) {
            $result->add("#");
        }
        for ($j = 0; $j < $countPlies; $j++) {
            $a = array_slice($p, 0, $j + 1);
            $arr = [$a];
            $count_a = count($a);
            $value = implode('/', $a);
            $result->add($value . "/#");
            $complete = false;
            if ($count_a == $countPlies) {
                $complete = true;
                $result->add($value);
            }
            for ($i = 0; $i < $count_a; $i++) {
                $temp = [];
                foreach ($arr as $one) {
                    $this->help_replace_plus($one, $temp, $result, $complete, $isSYS);
                }
                $arr = $temp;
            }
        }
        return $result;
    }

    protected function help_replace_plus($arr, &$temp, &$result, $complete, $isSYS)
    {
        $count = count($arr);
        $m = 0;
        if ($isSYS) $m = 1;
        for ($i = $m; $i < $count; $i++) {
            $new = $arr;
            if ($new[$i] == '+') continue;
            $new[$i] = '+';
            $temp[] = $new;
            $value = implode('/', $new);
            $result->add($value . "/#");
            if ($complete) {
                $result->add($value);
            }
        }
    }

    /**
     * 清除Uid的订阅
     * @param $uid
     */
    public function my_clearUidSub($uid)
    {
        if (empty($uid)) return;
        foreach ($this->subArr as $sub) {
            $sub->remove($uid);
        }
    }

    /**
     * @param $topic
     * @param $data
     * @param $excludeUids
     * @throws \Exception
     */
    public function th_pub($topic, $data, $excludeUids = [])
    {
        $tree = $this->buildTrees($topic);
        foreach ($tree as $one) {
            if (isset($this->subArr[$one])) {
                foreach ($this->subArr[$one] as $uid) {
                    if (!in_array($uid, $excludeUids)) {
                        get_instance()->pubToUid($uid, $data, $topic);
                    }
                }
            }
        }
    }

    /**
     * 增加一个
     * @param $node_name
     * @param $uid
     * @throws \Server\Asyn\MQTT\Exception
     */
    public function th_addUid($node_name, $uid)
    {
        if (!isset($this->map[$node_name])) {
            $this->map[$node_name] = new Set();
        }
        $this->map[$node_name]->add($uid);
        if (Start::isLeader()) {
            get_instance()->pub('$SYS/uidcount', $this->countOnline());
        }
    }

    /**
     * 减少一个
     * @param $node_name
     * @param $uid
     * @throws \Server\Asyn\MQTT\Exception
     */
    public function th_removeUid($node_name, $uid)
    {
        if (isset($this->map[$node_name])) {
            $this->map[$node_name]->remove($uid);
        }
        if (Start::isLeader()) {
            get_instance()->pub('$SYS/uidcount', $this->countOnline());
        }
    }

    /**
     * 同步
     * @param $node_name
     * @param $datas
     * @param $type
     */
    public function th_syncData($node_name, $datas, $type)
    {
        switch ($type) {
            case ClusterProcess::TYPE_UID:
                if (!isset($this->map[$node_name])) {
                    $this->map[$node_name] = new Set($datas);
                } else {
                    $this->map[$node_name]->add(...$datas);
                }
                secho("CLUSTER", "同步$node_name uid信息");
                break;
            case ClusterProcess::TYPE_ACTOR:
                if (!isset($this->actorMap[$node_name])) {
                    $this->actorMap[$node_name] = new Set($datas);
                } else {
                    $this->actorMap[$node_name]->add(...$datas);
                }
                secho("CLUSTER", "同步$node_name actor信息");
                break;
        }
    }

    /**
     * @param int $index
     */
    public function updateFromConsul($index = 0)
    {
        $this->consul->setMethod('GET')
            ->setQuery(['index' => $index])
            ->execute("/v1/catalog/nodes", function ($data) use ($index) {
                if ($data['statusCode'] < 0) {
                    $this->updateFromConsul($index);
                    return;
                }
                $body = json_decode($data['body'], true);
                //寻找增加的
                $index = 0;
                foreach ($body as $value) {
                    $node_name = $value['Node'];
                    $ips = $value['TaggedAddresses'];
                    if (!isset($ips['lan'])) continue;
                    if ($ips['lan'] == getBindIp()){
                        $this->node_index = $index;
                        continue;
                    }
                    if (!isset($this->client[$node_name])) {
                        $this->addNode($node_name, $ips['lan']);
                    }
                    $index++;
                }
                //寻找减少的
                foreach ($this->client as $node_name => $client) {
                    $find = false;
                    foreach ($body as $value) {
                        $one_node_name = $value['Node'];
                        if ($one_node_name == $node_name) {
                            $find = true;
                            break;
                        }
                    }
                    if (!$find) {
                        $this->removeNode($node_name);
                    }
                }
                $index = $data['headers']['x-consul-index'];
                $this->updateFromConsul($index);
            });

    }

    /**
     * 添加一个Node
     * @param $node_name
     * @param $ip
     */
    protected function addNode($node_name, $ip)
    {
        new ClusterClient($ip, $this->port, function (ClusterClient $client) use ($node_name) {
            //同步uid
            $content = [];
            foreach ($this->map[$this->node_name] as $value) {
                $content[] = $value;
                if (count($content) >= 10000) {
                    $client->syncNodeData($this->node_name, $content, ClusterProcess::TYPE_UID);
                    $content = [];
                }
            }
            if (count($content) > 0) {
                $client->syncNodeData($this->node_name, $content, ClusterProcess::TYPE_UID);
            }

            if (!isset($this->map[$node_name])) {
                $this->map[$node_name] = new Set();
            }
            //同步Actor
            $content = [];
            foreach ($this->actorMap[$this->node_name] as $value) {
                $content[] = $value;
                if (count($content) >= 10000) {
                    $client->syncNodeData($this->node_name, $content, ClusterProcess::TYPE_ACTOR);
                    $content = [];
                }
            }
            if (count($content) > 0) {
                $client->syncNodeData($this->node_name, $content, ClusterProcess::TYPE_ACTOR);
            }
            if (!isset($this->actorMap[$node_name])) {
                $this->actorMap[$node_name] = new Set();
            }
            $this->client[$node_name] = $client;
            if (Start::isLeader()) {
                get_instance()->pub('$SYS/nodes', array_keys($this->map));
            }
        });
    }

    /**
     * 移除一个Node
     * @param $node_name
     * @throws \Server\Asyn\MQTT\Exception
     */
    protected function removeNode($node_name)
    {
        unset($this->map[$node_name]);
        $this->client[$node_name]->close();
        unset($this->client[$node_name]);
        if (Start::isLeader()) {
            get_instance()->pub('$SYS/nodes', array_keys($this->map));
        }
    }

    /**
     * 查找uid在哪个node
     * @param $uid
     * @return bool|int|string
     */
    protected function searchUid($uid)
    {
        if (empty($uid)) return false;
        foreach ($this->map as $node_name => $set) {
            if ($set->contains($uid)) {
                return $node_name;
            }
        }
        return false;
    }

    /**
     * 获取topic数量
     * @param $topic
     * @return int
     */
    public function my_getSubMembersCount($topic)
    {
        $count = $this->th_getSubMembersCount($topic);
        foreach ($this->client as $client) {
            $token = $client->getSubMembersCount($topic);
            $one_count = $client->getTokenResult($token);
            $count += $one_count;
        }
        return $count;
    }

    /**
     * 获取topic数量
     * @param $topic
     * @return int
     */
    public function th_getSubMembersCount($topic)
    {
        if (array_key_exists($topic, $this->subArr) && !empty($this->subArr[$topic])) {
            return $this->subArr[$topic]->count();
        } else {
            return 0;
        }
    }

    /**
     * 获取topic Members
     * @param $topic
     * @return array
     */
    public function my_getSubMembers($topic)
    {
        $array = $this->th_getSubMembers($topic);
        foreach ($this->client as $client) {
            $token = $client->getSubMembers($topic);
            $one_array = $client->getTokenResult($token);
            $array = array_merge($array, $one_array);
        }
        return $array;
    }

    /**
     * 获取topic Members
     * @param $topic
     * @return array
     */
    public function th_getSubMembers($topic)
    {
        if (array_key_exists($topic, $this->subArr) && !empty($this->subArr[$topic])) {
            return $this->subArr[$topic]->toArray();
        } else {
            return [];
        }
    }

    /**
     * 获取uid的所有订阅
     * @param $uid
     * @return mixed
     */
    public function my_getUidTopics($uid)
    {
        $array = $this->th_getUidTopics($uid);
        foreach ($this->client as $client) {
            $token = $client->getUidTopics($uid);
            $one_array = $client->getTokenResult($token);
            $array = array_merge($array, $one_array);
        }
        return $array;
    }

    /**
     * 获取uid的所有订阅
     * @param $uid
     * @return mixed
     */
    public function th_getUidTopics($uid)
    {
        $arr = [];
        foreach ($this->subArr as $topic => $set) {
            if ($set->contains($uid)) {
                $arr[] = $topic;
            }
        }
        return $arr;
    }

    /**
     * 获取所有的Sub
     * @return array
     */
    public function my_getAllSub()
    {
        $status[$this->node_name] = $this->th_getAllSub();
        foreach ($this->client as $node_name => $client) {
            $token = $client->getAllSub();
            $status[$node_name] = $client->getTokenResult($token);
        }
        return $status;
    }

    /**
     * @return array
     */
    public function th_getAllSub()
    {
        $status = [];
        foreach ($this->subArr as $key => $value) {
            $status[$key] = $value->count();
        }
        return $status;
    }

    /**
     * 是否在线
     * @param $uid
     * @return bool
     */
    public function isOnline($uid)
    {
        if (empty($uid)) return false;
        foreach ($this->map as $node_name => $set) {
            if ($set->contains($uid)) {
                return true;
            }
            return false;
        }
    }

    /**
     * 获取在线数量
     * @return int
     */
    public function countOnline()
    {
        $sum = 0;
        foreach ($this->map as $node_name => $set) {

            $sum += $set->count();
        }
        return $sum;
    }

    /**
     * 获取所有的uids
     * @return array
     */
    public function getAllUids()
    {
        $uids = new Set();
        foreach ($this->map as $node_name => $set) {
            foreach ($set as $value) {
                $uids->add($value);
            }
        }
        return $uids->toArray();
    }

    /**
     * 获取状态
     */
    public function getStatus()
    {
        $data['nodes'] = array_keys($this->map);
        $data['count'] = $this->countOnline();
        return $data;
    }

    /**
     * 设置debug模式
     * @param $node_name
     * @param $bool
     */
    public function my_setDebug($node_name, $bool)
    {
        if ($node_name == getNodeName()) {
            Start::setDebug($bool);
        } else {
            if (array_key_exists($node_name, $this->client)) {
                $this->client[$node_name]->setDebug($bool);
            }
        }
    }

    /**
     * reload
     * @param $node_name
     */
    public function my_reload($node_name)
    {
        if ($node_name == getNodeName()) {
            get_instance()->server->reload();
        } else {
            if (array_key_exists($node_name, $this->client)) {
                $this->client[$node_name]->reload();
            }
        }
    }

    /**
     * 发送状态
     * @throws \Server\Asyn\MQTT\Exception
     */
    public function my_status()
    {
        get_instance()->getStatus();
        foreach ($this->client as $client) {
            $client->status();
        }
    }

    /**
     * 发送状态
     * @throws \Server\Asyn\MQTT\Exception
     */
    public function th_status()
    {
        get_instance()->getStatus();
    }

    /**
     * 获取节点
     * @return array
     */
    public function getNodes()
    {
        return array_keys($this->map);
    }

    /**
     * 获取uid相关的信息
     * @param $uid
     * @return null
     */
    public function my_getUidInfo($uid)
    {
        $node_name = $this->searchUid($uid);
        if ($node_name) {
            if (!isset($this->client[$node_name])) return null;
            $token = $this->client[$node_name]->getUidInfo($uid);
            $fdInfo = $this->client[$node_name]->getTokenResult($token);
            return $fdInfo;
        }
        return null;
    }

    /**
     * 获取统计信息
     * @param $node_name
     * @param $index
     * @param $num
     * @return null
     */
    public function my_getStatistics($node_name, $index, $num)
    {
        if (!isset($this->client[$node_name])) return null;
        $token = $this->client[$node_name]->getStatistics($index, $num);
        $map = $this->client[$node_name]->getTokenResult($token);
        return $map;
    }

    protected function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }
}