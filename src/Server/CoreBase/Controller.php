<?php
namespace Server\CoreBase;

use Server\SwooleMarco;

/**
 * Controller 控制器
 * 对象池模式，实例会被反复使用，成员变量缓存数据记得在销毁时清理
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 上午11:59
 */
class Controller extends CoreBase
{
    /**
     * @var \Server\DataBase\RedisAsynPool
     */
    public $redis_pool;
    /**
     * @var \Server\DataBase\MysqlAsynPool
     */
    public $mysql_pool;
    /**
     * @var HttpInPut
     */
    public $http_input;
    /**
     * @var HttpOutPut
     */
    public $http_output;
    /**
     * 是否来自http的请求不是就是来自tcp
     * @var string
     */
    public $request_type;
    /**
     * @var \Server\Client\Client
     */
    public $client;
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
     * http response
     * @var \swoole_http_request
     */
    protected $request;
    /**
     * http response
     * @var \swoole_http_response
     */
    protected $response;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->http_input = new HttpInput();
        $this->http_output = new HttpOutput($this);
        $this->redis_pool = get_instance()->redis_pool;
        $this->mysql_pool = get_instance()->mysql_pool;
        $this->client = get_instance()->client;
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
        $this->request_type = SwooleMarco::TCP_REQUEST;
    }

    /**
     * set http Request Response
     * @param $request
     * @param $response
     */
    public function setRequestResponse($request, $response)
    {
        $this->request = $request;
        $this->response = $response;
        $this->http_input->set($request);
        $this->http_output->set($response);
        $this->request_type = SwooleMarco::HTTP_REQUEST;
    }

    /**
     * 异常的回调
     * @param \Exception $e
     */
    public function onExceptionHandle(\Exception $e)
    {
        switch ($this->request_type) {
            case SwooleMarco::HTTP_REQUEST:
                $this->http_output->end($e->getMessage());
                break;
            case SwooleMarco::TCP_REQUEST:
                $this->send($e->getMessage());
                break;
        }
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
     * 销毁
     */
    public function destroy()
    {
        parent::destroy();
        unset($this->fd);
        unset($this->uid);
        unset($this->client_data);
        unset($this->request);
        unset($this->response);
        $this->http_input->reset();
        $this->http_output->reset();
        ControllerFactory::getInstance()->revertController($this);
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
        get_instance()->sendToGroup($groupId, $data);
        if ($distory) {
            $this->destroy();
        }
    }
}