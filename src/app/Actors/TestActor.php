<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-1-2
 * Time: 下午3:24
 */

namespace app\Actors;


use Server\CoreBase\Actor;

class TestActor extends Actor
{

    public function test()
    {
        $this->setStatus('status', 1);
    }


    /**
     * 处理注册状态
     * @param $key
     * @param $value
     */
    public function registStatusHandle($key, $value)
    {
        switch ($key) {
            case 'status':
                switch ($value) {
                    case 1:
                        $this->tick(100, function () {
                            echo "1\n";
                        });
                        break;
                }
                break;
        }
    }
}