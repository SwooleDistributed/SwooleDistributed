<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-15
 * Time: 上午10:52
 */

namespace Server\Components\Cluster;

use Server\Asyn\HttpClient\HttpClient;
use Server\Components\Process\Process;

class ClusterProcess extends Process
{
    protected $map = [];
    protected $client = [];
    protected $node_name;
    protected $port;
    /**
     * @var HttpClient
     */
    protected $consul;

    public function start($process)
    {
        parent::start($process);
        $this->node_name = $this->config['consul']['node_name'];
        $this->map[$this->node_name] = [];
        foreach (get_instance()->server->connections as $fd) {
            $fdinfo = get_instance()->server->connection_info($fd);
            $uid = $fdinfo['uid'] ?? null;
            if ($uid != null) {
                $this->map[$this->node_name][$uid] = [];
            }
        }
        $this->consul = new HttpClient(null, 'http://127.0.0.1:8500');
        $this->port = $this->config['cluster']['port'];
        swoole_timer_after(1000, function () {
            $this->updateFromConsul();
        });
    }

    /**
     * 自身增加了一个uid
     * @param $uid
     * @param $session
     */
    public function my_addUid($uid, $session = [])
    {
        $this->map[$this->node_name][$uid] = $session;
        foreach ($this->client as $client) {
            $client->addNodeUid($this->node_name, $uid, $session);
        }
    }

    /**
     * 获取Session
     * @param $uid
     * @return null
     */
    public function my_getSession($uid)
    {
        return $this->map[$this->node_name][$uid] ?? [];
    }

    /**
     * 自身减少了一个uid
     * @param $uid
     */
    public function my_removeUid($uid)
    {
        unset($this->map[$this->node_name][$uid]);
        foreach ($this->client as $client) {
            $client->removeNodeUid($this->node_name, $uid);
        }
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
     * 增加一个
     * @param $node_name
     * @param $uid
     * @param $session
     */
    public function th_addUid($node_name, $uid, $session)
    {
        $this->map[$node_name][$uid] = $session;
    }

    /**
     * 减少一个
     * @param $node_name
     * @param $uid
     */
    public function th_removeUid($node_name, $uid)
    {
        unset($this->map[$node_name][$uid]);
    }

    /**
     * 同步
     * @param $node_name
     * @param $uids
     */
    public function th_syncData($node_name, $uids)
    {
        $this->map[$node_name] = $uids;
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
                    if ($ips['lan'] == $this->config['consul']['bind_addr']) continue;
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
            $client->syncNodeData($this->node_name, $this->map[$this->node_name]);
            $this->client[$node_name] = $client;
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
        unset($this->map[$node_name]);
    }

    /**
     * 查找uid在哪个node
     * @param $uid
     * @return bool|int|string
     */
    protected function searchUid($uid)
    {
        foreach ($this->map as $node_name => $array) {
            if (isset($array[$uid])) {
                return $node_name;
            }
        }
        return false;
    }

    /**
     * 是否在线
     * @param $uid
     * @return bool
     */
    public function isOnline($uid)
    {
        foreach ($this->map as $node_name => $array) {
            if (isset($array[$uid])) {
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
        foreach ($this->map as $node_name => $array) {
            $sum += count($array);
        }
        return $sum;
    }

    /**
     * @return array
     */
    public function getUidMap()
    {
        return $this->map;
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