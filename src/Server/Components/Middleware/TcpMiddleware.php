<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-9-28
 * Time: 下午2:49
 */

namespace Server\Components\Middleware;

abstract class TcpMiddleware extends Middleware
{
    protected $fd;
    protected $response;

    public function init($fd)
    {
        $this->fd = $fd;
    }

    /**
     * sendToUid
     * @param $data
     */
    protected function send($data)
    {
        get_instance()->send($this->fd, $data, true);
    }

    protected function close()
    {
        get_instance()->close($this->fd);
        throw new \Exception('close');
    }
}