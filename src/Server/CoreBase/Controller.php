<?php
namespace Server\CoreBase;
/**
 * Controller 控制器
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 上午11:59
 */
class Controller extends CoreBase
{
    /**
     * fd
     * @var int
     */
    protected $fd;
    /**
     * uid
     * @var int
     */
    protected $uid;
    /**
     * 用户数据
     * @var
     */
    protected $client_data;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 设置客户端协议数据
     * @param $uid
     * @param $fd
     * @param $client_data
     */
    public function setClientData($uid, $fd, $client_data)
    {
        $this->uid = $uid;
        $this->fd = $fd;
        $this->client_data = $client_data;
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
        unset($this->server);
        unset($this->fd);
        unset($this->client_data);
        ControllerFactory::getInstance()->revertController($this);
    }

    /**
     * 向当前客户端发送消息
     * @param $data
     * @param $distory
     * @throws SwooleException
     */
    protected function send($data, $distory = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is distory can not send data');
        }
        $data = get_instance()->encode($this->pack->pack($data));
        get_instance()->server->send($this->fd, $data);
        if ($distory) {
            $this->destroy();
        }
    }

    /**
     * sendToUid
     * @param $uid
     * @param $data
     * @throws SwooleException
     */
    protected function sendToUid($uid, $data, $distory = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is distory can not send data');
        }
        get_instance()->sendToUid($uid, $data);
        if ($distory) {
            $this->destroy();
        }
    }

    /**
     * sendToUids
     * @param $uids
     * @param $data
     * @param $distory
     * @throws SwooleException
     */
    protected function sendToUids($uids, $data, $distory = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is distory can not send data');
        }
        get_instance()->sendToUids($uids, $data);
        if ($distory) {
            $this->destroy();
        }
    }

    /**
     * sendToAll
     * @param $data
     * @param $distory
     * @throws SwooleException
     */
    protected function sendToAll($data, $distory = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is distory can not send data');
        }
        get_instance()->sendToAll($data);
        if ($distory) {
            $this->destroy();
        }
    }

    /**
     * 发送给群
     * @param $groupId
     * @param $data
     * @param bool $distory
     * @throws SwooleException
     */
    protected function sendToGroup($groupId, $data, $distory = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is distory can not send data');
        }
        get_instance()->sendToGroup($groupId,$data);
        if ($distory) {
            $this->destroy();
        }
    }
}