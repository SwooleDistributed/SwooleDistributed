<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-8-14
 * Time: 下午2:55
 */

namespace Server\Components\SDHelp;

use Server\Components\Consul\ConsulLeader;
use Server\Components\Process\Process;
use Server\Components\Reload\InotifyReload;
use Server\Components\TimerTask\TimerTask;

class SDHelpProcess extends Process
{
    public $data = [];
    protected $statisticsMap = [];
    public function start($process)
    {
        new TimerTask();
        if (get_instance()->config->get('consul.enable', false)) {
            new ConsulLeader($this);
        }
        if (get_instance()->config->get('auto_reload_enable', false)) {//代表启动单独进程进行reload管理
            new InotifyReload();
        }
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function getData($name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * @param $dataName
     * @param $data
     * @return bool
     */
    public function setData($dataName, $data)
    {
        $this->data[$dataName] = $data;
        return true;
    }

    /**
     * 添加统计
     * @param $path
     * @param $time
     */
    public function addStatistics($path, $time)
    {
        if (!array_key_exists($path, $this->statisticsMap)) {
            $this->statisticsMap[$path]['times'] = 0;
            $this->statisticsMap[$path]['used'] = 0;
            $this->statisticsMap[$path]['min'] = 9999999;
            $this->statisticsMap[$path]['max'] = 0;
        }
        $this->statisticsMap[$path]['times']++;
        $this->statisticsMap[$path]['used'] += $time;
        if ($time < $this->statisticsMap[$path]['min']) {
            $this->statisticsMap[$path]['min'] = $time;
        }
        if ($time > $this->statisticsMap[$path]['max']) {
            $this->statisticsMap[$path]['max'] = $time;
        }
    }

    /**
     * 获取统计数据
     * @param int $index
     * @param int $num
     * @return array
     */
    public function getStatistics($index = -1, $num = 100)
    {
        if ($index < 0) {
            return $this->statisticsMap;
        }
        $data['total'] = ceil(count($this->statisticsMap) / $num);
        $data['data'] = array_slice($this->statisticsMap, $num * $index, $num);
        return $data;
    }

    protected function onShutDown()
    {
        // TODO: Implement onShutDown() method.
    }
}