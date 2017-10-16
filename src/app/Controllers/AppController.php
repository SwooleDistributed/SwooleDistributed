<?php

namespace app\Controllers;

use app\Models\AppModel;
use Server\CoreBase\Controller;

/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午3:51
 */
class AppController extends Controller
{
    /**
     * @var AppModel
     */
    public $AppModel;

    protected function initialization($controller_name, $method_name)
    {
        parent::initialization($controller_name, $method_name);
        $this->AppModel = $this->loader->model('AppModel', $this);
    }

    public function sub($sub)
    {
        $this->bindUid($this->fd);
        $this->addSub($sub);
        $this->send("ok.$this->fd");
    }

    public function http_remove()
    {
        $sub = $this->http_input->get('sub');
        $fd = (int)$this->http_input->get('fd');
        get_instance()->removeSub($sub, $fd);
        $this->http_output->end("ok");
    }

    public function pub($sub, $data)
    {
        $this->sendPub($sub, $data);
    }

    public function http_pub()
    {
        $this->sendPub('test', 1);
    }

    public function sendAll($data)
    {
        $this->sendToAll($data);
    }

    public function onClose()
    {
        var_dump('close');
        $this->destroy();
    }

    public function onConnect()
    {
        var_dump('connect');
        $this->destroy();
    }

    public function http_test()
    {
        $this->http_output->end(1123);
    }
}