<?php
namespace Server\Controllers;

use Server\CoreBase\Controller;
use Server\Tasks\TestTask;

/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午3:51
 */
class TestController extends Controller
{
    /**
     * @var TestTask
     */
    public $testTask;

    /**
     * mysql 测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function mysql_test()
    {
        $this->mysql_pool->dbQueryBuilder->select('*')->from('account')->where('sex', 1);
        $this->mysql_pool->query(function ($result) {
            print_r($result);
        });
        $this->destroy();
    }

    /**
     * mysql 事务测试
     */
    public function mysql_begin_test()
    {
        $id = $this->mysql_pool->begin($this);
        $this->mysql_pool->dbQueryBuilder->select('*')->from('account')->where('uid', 10004);
        $this->mysql_pool->query(function ($result) {
            print_r($result);
        },$id);
        $this->mysql_pool->dbQueryBuilder->update('account')->set('channel','8888')->where('uid', 10004);
        $this->mysql_pool->query(function ($result) {
            print_r($result);
        },$id);
        $this->mysql_pool->commit($this);
    }

    /**
     * 绑定uid
     */
    public function bind_uid()
    {
        get_instance()->bindUid($this->fd, $this->client_data->data);
        $this->destroy();
    }

    /**
     * 效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function efficiency_test()
    {
        $data = $this->client_data->data;
        $this->sendToUid(mt_rand(1,100), $data);
    }
    /**
     * 效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function efficiency_test2()
    {
        $data = $this->client_data->data;
        $this->send($data);
    }
    /**
     * 测试redis效率
     */
    public function ansy_redis_test()
    {
        $this->redis_pool->get('test', function ($result) {
            $this->send($this->client_data->data);
        });
    }

    /**
     * http测试
     */
    public function http_test()
    {
        $this->http_output->end('hello');
    }

    /**
     * html测试
     */
    public function http_html_test()
    {
        $template = $this->loader->view('server::error_404');
        $this->http_output->end($template->render(['controller'=>'TestController\html_test','message'=>'页面不存在！']));
    }
    /**
     * html测试
     */
    public function http_html_file_test()
    {
       $this->http_output->endFile(SERVER_DIR,'Views/test.html');
    }

}