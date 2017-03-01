<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-2-28
 * Time: 下午3:19
 */

namespace Server\Coroutine;

/**
 * 熔断器
 * Class Fuse
 * @package Server\Coroutine
 */
class Fuse
{
    /**
     * 开
     */
    const OPEN = 1;
    /**
     * 关
     */
    const CLOSE = 2;
    /**
     * 尝试状态
     */
    const TRY = 3;
    /**
     * 阀值
     */
    const THRESHOLD = 0.01;
    /**
     * 检查时间
     */
    const CHECKTIME = 2000;
    /**
     * 尝试打开的间隔
     */
    const TRYTIME = 1000;
    /**
     * 尝试多少个
     */
    const TRYMAX = 1;


    private static $instance;

    private $fuses = [];

    public function __construct()
    {
        self::$instance = $this;
    }

    /**
     * @return Fuse
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            new Fuse();
        }
        return self::$instance;
    }

    /**
     * 请求运行返回获取熔断器状态
     * @param $topic
     * @return int
     */
    public function requestRun($topic)
    {
        if (!array_key_exists($topic, $this->fuses)) {
            $this->fuses[$topic] = ['success' => 0, 'fail' => 0, 'time' => getTickTime(), 'state' => self::OPEN, 'stoptime' => 0, 'trycount' => 0, 'trysuccess' => 0];
            return self::OPEN;
        }
        $fuse = &$this->fuses[$topic];
        switch ($fuse['state']) {
            case self::CLOSE://断路状态
                if (getTickTime() - $fuse['stoptime'] >= self::TRYTIME) {//1秒后尝试打开
                    $fuse['state'] = self::TRY;
                    $fuse['trycount'] = 0;
                    $fuse['trysuccess'] = 0;
                    return self::OPEN;
                } else {
                    return self::CLOSE;
                }
                break;
            case self::TRY://尝试状态
                if ($fuse['trysuccess'] >= self::TRYMAX) {//全部都尝试成功，那么可以放开流量试试
                    $fuse['state'] = self::OPEN;
                    $fuse['trysuccess'] = 0;
                    return self::OPEN;
                }
                if ($fuse['trycount'] < self::TRYMAX) {//放开1次请求看看情况
                    $fuse['trycount']++;
                    return self::OPEN;
                } else {
                    return self::CLOSE;
                }
                break;
            case self::OPEN://正常状态
                $total = ($fuse['success'] + $fuse['fail']);
                if ($total == 0) return self::OPEN;
                $threshold = $fuse['fail'] / ($fuse['success'] + $fuse['fail']);
                if ($threshold > self::THRESHOLD) {
                    $fuse['state'] = self::CLOSE;
                    $fuse['stoptime'] = getTickTime();
                    return self::CLOSE;
                } else {
                    return self::OPEN;
                }
                break;
        }
    }

    /**
     * 提交超时或者失败
     * @param $topic
     */
    public function commitTimeOutOrFaile($topic)
    {
        $fuse = &$this->fuses[$topic];
        $this->cleanData($fuse);
        if ($fuse['state'] == self::CLOSE) {
            return;
        }
        $fuse['fail']++;
        //尝试状态下出现一次错误就直接close
        if ($fuse['state'] == self::TRY) {
            $fuse['trysuccess'] = 0;
            $fuse['state'] = self::CLOSE;
            $fuse['stoptime'] = getTickTime();
        }
    }

    /**
     * 清理过时数据
     * @param $fuse
     */
    private function cleanData(&$fuse)
    {
        if (getTickTime() - $fuse['time'] > self::CHECKTIME) {
            $fuse['success'] = 0;
            $fuse['fail'] = 0;
            $fuse['time'] = getTickTime();
        }
    }

    /**
     * 提交成功
     * @param $topic
     */
    public function commitSuccess($topic)
    {
        $fuse = &$this->fuses[$topic];
        $this->cleanData($fuse);
        $fuse['success']++;
        if ($fuse['state'] == self::TRY) {
            $fuse['trysuccess']++;
        }
    }
}