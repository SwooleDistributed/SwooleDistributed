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
use Server\Components\Process\Process;

class ClusterProcess extends Process
{
    protected $map = [];
    protected $client = [];
    protected $node_name;
    protected $port;
    protected $subArr = [];
    /**
     * @var HttpClient
     */
    protected $consul;

    public function start($process)
    {
        if (get_instance()->isCluster()) {
            $this->node_name = getNodeName();
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
            swoole_timer_after(1000, function () {
                $this->updateFromConsul();
            });
        }
    }

    /**
     * 自身增加了一个uid
     * @param $uid
     */
    public function my_addUid($uid)
    {
        $this->map[$this->node_name]->add($uid);
        foreach ($this->client as $client) {
            $client->addNodeUid($this->node_name, $uid);
        }
    }

    /**
     * 自身减少了一个uid
     * @param $uid
     */
    public function my_removeUid($uid)
    {
        if (get_instance()->isCluster()) {
            $this->map[$this->node_name]->remove($uid);
            foreach ($this->client as $client) {
                $client->removeNodeUid($this->node_name, $uid);
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
        $node_name = $this->searchUid($uid);
        if ($node_name) {
            $this->client[$node_name]->kickUid($uid);
        }
    }

    /**
     * @param $uid
     * @param $data
     */
    public function my_sendToUid($uid, $data)
    {
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
                if (isset($array[$uid])) {
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

    /**
     * 添加订阅
     * @param $topic
     * @param $uid
     */
    public function my_addSub($topic, $uid)
    {
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
        if (isset($this->subArr[$topic])) {
            $this->subArr[$topic]->remove($uid);
        }
    }

    /**
     * 发布订阅
     * @param $topic
     * @param $data
     */
    public function my_pub($topic, $data)
    {
        $this->th_pub($topic, $data);
        foreach ($this->client as $client) {
            $client->pub($topic, $data);
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
        $p = explode("/", $topic);
        $countPlies = count($p);
        $result = new Set();
        $result->add("#");
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
                    $this->help_replace_plus($one, $temp, $result, $complete);
                }
                $arr = $temp;
            }
        }
        return $result;
    }

    protected function help_replace_plus($arr, &$temp, &$result, $complete)
    {
        $count = count($arr);
        for ($i = 0; $i < $count; $i++) {
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
        foreach ($this->subArr as $sub) {
            $sub->remove($uid);
        }
    }

    /**
     * @param $topic
     * @param $data
     */
    public function th_pub($topic, $data)
    {
        $tree = $this->buildTrees($topic);
        foreach ($tree as $one) {
            if (isset($this->subArr[$one])) {
                foreach ($this->subArr[$one] as $uid) {
                    get_instance()->pubToUid($uid, $data, $topic);
                }
            }
        }
    }

    /**
     * 增加一个
     * @param $node_name
     * @param $uid
     */
    public function th_addUid($node_name, $uid)
    {
        if (!isset($this->map[$node_name])) {
            $this->map[$node_name] = new Set();
        }
        $this->map[$node_name]->add($uid);
    }

    /**
     * 减少一个
     * @param $node_name
     * @param $uid
     */
    public function th_removeUid($node_name, $uid)
    {
        if (isset($this->map[$node_name])) {
            $this->map[$node_name]->remove($uid);
        }
    }

    /**
     * 同步
     * @param $node_name
     * @param $uids
     */
    public function th_syncData($node_name, $uids)
    {
        if (!isset($this->map[$node_name])) {
            $this->map[$node_name] = new Set($uids);
        } else {
            $this->map[$node_name]->add(...$uids);
        }
        echo "同步$node_name 信息\n";
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
                foreach ($body as $value) {
                    $node_name = $value['Node'];
                    $ips = $value['TaggedAddresses'];
                    if (!isset($ips['lan'])) continue;
                    if ($ips['lan'] == getBindIp()) continue;
                    if (!isset($this->client[$node_name])) {
                        $this->addNode($node_name, $ips['lan']);
                    }
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
            $content = [];
            foreach ($this->map[$this->node_name] as $value) {
                $content[] = $value;
                if (count($content) >= 10000) {
                    $client->syncNodeData($this->node_name, $content);
                    $content = [];
                }
            }
            if (count($content) > 0) {
                $client->syncNodeData($this->node_name, $content);
            }
            $this->client[$node_name] = $client;
            $this->map[$node_name] = new Set();
        });
    }

    /**
     * 移除一个Node
     * @param $node_name
     */
    protected function removeNode($node_name)
    {
        unset($this->map[$node_name]);
        $this->client[$node_name]->close();
        unset($this->client[$node_name]);
    }

    /**
     * 查找uid在哪个node
     * @param $uid
     * @return bool|int|string
     */
    protected function searchUid($uid)
    {
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
    public function getSubMembersCount($topic)
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
    public function getSubMembers($topic)
    {
        if (array_key_exists($topic, $this->subArr) && !empty($this->subArr[$topic])) {
            return $this->subArr[$topic]->toArray();
        } else {
            return [];
        }
    }

    /**
     * 是否在线
     * @param $uid
     * @return bool
     */
    public function isOnline($uid)
    {
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
}