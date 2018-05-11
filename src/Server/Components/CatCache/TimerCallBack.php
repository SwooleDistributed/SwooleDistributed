<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-12-28
 * Time: 下午12:16
 */

namespace Server\Components\CatCache;


use Server\Components\Event\Event;
use Server\Components\Event\EventDispatcher;
use Server\CoreBase\Child;
use Server\Memory\Pool;

class TimerCallBack
{
    const KEY = "timer_callback";

    public static function ack($token)
    {
        CatCacheRpcProxy::getRpc()->ackTimerCallBack($token);
    }

    /**
     * @param $after_time
     * @param $model_name
     * @param $model_fuc
     * @param $param_arr
     * @return string
     */
    public static function addTimer($after_time, $model_name, $model_fuc, $param_arr = [])
    {
        if ($param_arr == null) {
            $param_arr = [];
        }
        $data = [
            "model_name" => $model_name,
            "model_fuc" => $model_fuc,
            "param_arr" => $param_arr,
        ];
        return CatCacheRpcProxy::getRpc()->setTimerCallBack(time() + $after_time, $data);
    }

    /**
     * 初始化
     */
    public static function init()
    {
        EventDispatcher::getInstance()->add(TimerCallBack::KEY, function (Event $event) {
            $child = Pool::getInstance()->get(Child::class);
            $model = get_instance()->loader->model($event->data['model_name'], $child);
            sd_call_user_func_array([$model, $event->data['model_fuc']], $event->data['param_arr']);
            $child->destroy();
            Pool::getInstance()->push($child);
        });
    }
}