<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-12-30
 * Time: 下午3:33
 */

namespace Server\Test;


use Exception;
use Server\CoreBase\PortManager;

class TestRequest
{
    public $header = [];
    public $server = [];
    public $get = [];
    public $post = [];
    public $cookie = [];
    public $files = [];
    public $_rawContent = '';
    public $server_port;
    public $fd;

    public function __construct($path_info, $header = [], $get = [], $post = [], $cookie = [])
    {
        $this->server_port = $this->getHttpPort(get_instance()->config);
        $this->setControllerName($path_info);
        $this->header = $header;
        $this->get = $get;
        $this->post = $post;
        $this->cookie = $cookie;
    }

    public function getHttpPort($config)
    {
        $ports = $config->get('ports');
        foreach ($ports as $value) {
            if ($value['socket_type'] == PortManager::SOCK_HTTP) {
                return $value['socket_port'];
            }
        }
        throw new Exception('没有找到http端口');
    }

    /**
     * eq:/TestController/test
     * @param $path_info
     */
    public function setControllerName($path_info)
    {
        $this->server['path_info'] = $path_info;
    }

    public function rawContent()
    {
        return $this->_rawContent;
    }
}