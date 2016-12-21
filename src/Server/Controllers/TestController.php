<?php
namespace Server\Controllers;

use Server\CoreBase\Controller;
use Server\CoreBase\SwooleException;
use Server\Models\TestModel;
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
     * @var TestModel
     */
    public $testModel;

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
     * mysql 事务协程测试
     */
    public function http_mysql_begin_coroutine_test()
    {
        $id = yield $this->mysql_pool->coroutineBegin($this);
        $update_result = yield $this->mysql_pool->dbQueryBuilder->update('user_info')->set('sex', '0')->where('uid', 36)->coroutineSend($id);
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from('user_info')->where('uid', 36)->coroutineSend($id);
        if ($result['result'][0]['channel'] == 888) {
            $this->http_output->end('commit');
            yield $this->mysql_pool->coroutineCommit($id);
        } else {
            $this->http_output->end('rollback');
            yield $this->mysql_pool->coroutineRollback($id);
        }
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
        $this->sendToUid(mt_rand(1, 100), $data);
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
     * mysql效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function mysql_efficiency()
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from('account')->where('uid', 10004)->coroutineSend();
        $this->send($this->client_data->data);
    }

    /**
     * mysql效率测试
     * @throws \Server\CoreBase\SwooleException
     */
    public function http_mysql_efficiency()
    {
        $uid = rand(36, 60);
        $result = yield $this->mysql_pool->dbQueryBuilder->update('user_info')->where('uid', $uid)->set('chat_status', 0)->set('call_record_id', 0)->coroutineSend();
        //print_r($result);
        $this->http_output->end(0);
    }

    /**
     * 测试redis效率
     */
    public function http_ansy_redis_test()
    {
        $this->redis_pool->mset(array('a1' => 'Joe', 'a2' => 'jeck'), function ($result) {
            var_dump($result);
        });
    }

    /**
     * http测试
     */
    public function http_test()
    {
        $this->http_output->end('hello', false);
    }

    /**
     * 协程测试
     */
    public function http_testCoroutine()
    {
        $this->testModel = $this->loader->model('TestModel', $this);
        $result = yield $this->testModel->test_coroutine();
        $this->http_output->end($result);
    }

    /**
     * 协程的controller可以直接抛出异常
     * 异常处理测试
     */
    public function http_testExceptionHandle()
    {
        $this->testModel = $this->loader->model('TestModel', $this);
        $result = yield $this->testModel->test_coroutine();
        throw new \Exception('ExceptionHandle');
    }

    /**
     * 普通的controller异常测试
     * @throws \Exception
     */
    public function http_testExceptionHandleII()
    {
        throw new SwooleException('ExceptionHandle');
    }

    /**
     * 普通model的异常测试
     * @throws \Exception
     */
    public function http_testExceptionHandleIII()
    {
        $this->testModel = $this->loader->model('TestModel', $this);
        $result = $this->testModel->test_exception();
    }

    /**
     * 协程的model报错
     */
    public function http_testExceptionHandleIV()
    {
        $this->testModel = $this->loader->model('TestModel', $this);
        $result = yield $this->testModel->test_exceptionII();
    }

    public function http_testRedis()
    {
        $this->redis_pool->get('test', function ($result) {
            $this->http_output->end($result);
        });
    }

    /**
     * html测试
     */
    public function http_html_test()
    {
        $template = $this->loader->view('server::error_404');
        $this->http_output->end($template->render(['controller' => 'TestController\html_test', 'message' => '页面不存在！']));
    }

    /**
     * html测试
     */
    public function http_html_file_test()
    {
        $this->http_output->endFile(SERVER_DIR, 'Views/test.html');
    }

    public function http_test_task()
    {
        $task = $this->loader->task('TestTask');
        $task->test();
        $result = yield $task->coroutineSend();
        $this->http_output->end($result);
    }

    public function http_test_request()
    {
        print_r($this->http_input->get('id'));
        $this->http_output->end('ok');
    }

    /**
     * 协程的httpclient测试
     */
    public function http_test_httpClient()
    {
        $httpClient = yield $this->client->coroutineGetHttpClient('http://localhost:8081');
        $result = yield $httpClient->coroutineGet("/TestController/test_request", ['id' => 123]);
        $this->http_output->end($result);
    }

    public function http_test_coroutineServer()
    {
        $result = yield get_instance()->coroutineUidIsOnline(1001);
        var_dump($result);
        $result = yield get_instance()->coroutineCountOnline();
        var_dump($result);
    }

    public function http_test_pdoMysql()
    {
        get_instance()->getMysql()->select('*')->from('account')->where('uid', 10004);
        $result = get_instance()->getMysql()->pdoQuery();
        var_dump($result);
    }

    public function http_redis_rpop()
    {
        $result = yield $this->redis_pool->coroutineSend('rPop', 'test');
        var_dump($result);
        $this->http_output->end($result);
    }

    public function http_redis_sadd()
    {
        $result = yield $this->redis_pool->coroutineSend('sadd', 'test123', [1, 2, 3, 4]);
        var_dump($result);
        $this->http_output->end($result);
    }

    /**
     * mysql
     * @throws \Server\CoreBase\SwooleException
     */
    public function http_mysql_batch()
    {
        $result = $this->mysql_pool->dbQueryBuilder->insertInto('account')->intoColumns(['network_id', 'name'])->intoValues([[1, 'test1'], [2, 'test2']])->getStatement(false);
        $this->mysql_pool->dbQueryBuilder->clear();
        $this->http_output->end($result);
    }

    public function http_addGroup()
    {
        get_instance()->addToGroup(1, 100000);

    }

    public function http_getGroups()
    {
        $result = yield get_instance()->coroutineGetAllGroups();
        var_dump($result);
    }

    public function http_getGroupCount()
    {
        $result = yield get_instance()->coroutineGetGroupCount(100000);
        var_dump($result);
    }

    public function http_getGroupUids()
    {
        $result = yield get_instance()->coroutineGetGroupUids(100000);
        var_dump($result);
    }

    public function http_delGroup()
    {
        $result = yield get_instance()->delGroup(100000);
        var_dump($result);
    }

    public function http_delGroups()
    {
        get_instance()->delAllGroups();
    }

    /**
     * @return boolean
     */
    public function isIsDestroy()
    {
        return $this->is_destroy;
    }

    public function http_test_pdo()
    {
        $testTask = $this->loader->task('TestTask');
        $testTask->testPdo();
        $testTask->startTask(null);
    }
}