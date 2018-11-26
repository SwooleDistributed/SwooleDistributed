<?php

namespace Server\CoreBase;

use Monolog\Logger;
use Server\Coroutine\Coroutine;
use Server\Start;
use Server\SwooleMarco;

/**
 * Controller 控制器
 * 对象池模式，实例会被反复使用，成员变量缓存数据记得在销毁时清理
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 上午11:59
 */
class Controller extends CoreBase
{
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
     * 用于单元测试模拟捕获服务器发出的消息
     * @var array
     */
    protected $testUnitSendStack = [];

    /**
     * 判断是不是RPC
     * @var bool
     */
    protected $isRPC;

    /**
     * rpc的token
     * @var string
     */
    protected $rpc_token;

    /**
     * @var string
     */
    protected $rpc_request_id;

    /**
     * Controller constructor.
     */
    final public function __construct()
    {
        parent::__construct();
        $this->http_input = new HttpInput();
        $this->http_output = new HttpOutput($this);
    }

    /**
     * 来自Tcp
     * 设置客户端协议数据
     * @param $uid
     * @param $fd
     * @param $client_data
     * @param $controller_name
     * @param $method_name
     * @param $params
     */
    public function setClientData($uid, $fd, $client_data, $controller_name, $method_name, $params)
    {
        $this->uid = $uid;
        $this->fd = $fd;
        $this->client_data = $client_data;
        if (isset($client_data->rpc_request_id)) {
            $this->isRPC = true;
            $this->rpc_token = $client_data->rpc_token ?? '';
            $this->rpc_request_id = $client_data->rpc_request_id ?? '';
        } else {
            $this->isRPC = false;
        }
        $this->request_type = SwooleMarco::TCP_REQUEST;
        $this->execute($controller_name, $method_name, $params);
    }

    /**
     * 来自Http
     * set http Request Response
     * @param $request
     * @param $response
     * @param $controller_name
     * @param $method_name
     * @param $params
     * @return \Generator
     */
    public function setRequestResponse($request, $response, $controller_name, $method_name, $params)
    {
        $this->request = $request;
        $this->response = $response;
        $this->http_input->set($request);
        $this->http_output->set($request, $response);
        $this->rpc_request_id = $this->http_input->header('rpc_request_id');
        $this->isRPC = empty($this->rpc_request_id) ? false : true;
        $this->request_type = SwooleMarco::HTTP_REQUEST;
        $this->execute($controller_name, $method_name, $params);
    }

    /**
     * @param $controller_name
     * @param $method_name
     * @param $params
     */
    private function execute($controller_name, $method_name, $params)
    {
        if (!is_callable([$this, $method_name])) {
            $method_name = 'defaultMethod';
        }
        Coroutine::startCoroutine(function () use ($controller_name, $method_name, $params) {
            try {
                yield $this->initialization($controller_name, $method_name);
                if ($params == null) {
                    yield call_user_func([$this, $method_name]);
                } else {
                    yield call_user_func_array([$this, $method_name], $params);
                }
            } catch (\Exception $e) {
                yield $this->onExceptionHandle($e);
            }
        });
    }

    /**
     * 初始化每次执行方法之前都会执行initialization
     * @param string $controller_name 准备执行的controller名称
     * @param string $method_name 准备执行的method名称
     * @throws \Exception
     */
    protected function initialization($controller_name, $method_name)
    {
        if ($this->isRPC && !empty($this->rpc_request_id)) {
            //全链路监控保证调用的request_id唯一
            $context = ['request_id' => $this->rpc_request_id];
        } else {
            $context = ['request_id' => time() . crc32($controller_name . $method_name . getTickTime() . rand(1, 10000000))];
        }
        $context['controller_name'] = $controller_name;
        $context['method_name'] = "$controller_name:$method_name";
        $this->setContext($context);
        $this->start_run_time = microtime(true);
        if (get_instance()->isDebug()) {
            set_time_limit(1);
        }
        $this->installMysqlPool($this->mysql_pool);
    }

    /**
     * ws追加设置Request
     * @param $request
     */
    public function setRequest($request)
    {
        $this->request = $request;
        $this->http_input->set($request);
    }

    /**
     * 异常的回调(如果需要继承$autoSendAndDestroy传flase)
     * @param \Exception $e
     * @param callable $handle
     */
    public function onExceptionHandle(\Exception $e, $handle = null)
    {
        //必须的代码
        if ($e instanceof SwooleRedirectException) {
            $this->http_output->setStatusHeader($e->getCode());
            $this->http_output->setHeader('Location', $e->getMessage());
            $this->http_output->end('end');
            return;
        }
        if ($e instanceof SwooleException) {
            print_r($e->getMessage() . "\n");
            $this->log($e->getMessage() . "\n" . $e->getTraceAsString(), Logger::ERROR);
            if ($e->others != null) {
                //这里只打印，在controller里面写日志，能把context带进去。
                print_r("=================================================\e[30;41m [ERROR] \e[0m==============================================================\n");
                print_r($e->others . "\n");
                print_r("\n");
                $this->log($e->others, Logger::NOTICE);
            }
        }
        //可以重写的代码
        if ($handle == null) {
            switch ($this->request_type) {
                case SwooleMarco::HTTP_REQUEST:
                    $this->http_output->end($e->getMessage());
                    break;
                case SwooleMarco::TCP_REQUEST:
                    $this->send($e->getMessage());
                    break;
            }
        } else {
            call_user_func($handle, $e);
        }
    }

    /**
     * 向当前客户端发送消息
     * @param $data
     * @param $destroy
     * @throws SwooleException
     */
    protected function send($data, $destroy = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is destroy can not send data');
        }
        if ($this->isRPC && !empty($this->rpc_token)) {
            $rpc_data['rpc_token'] = $this->rpc_token;
            $rpc_data['rpc_result'] = $data;
            $data = $rpc_data;
        }
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'send', 'fd' => $this->fd, 'data' => $data];
        } else {
            get_instance()->send($this->fd, $data, true);
        }
        if ($destroy) {
            $this->destroy();
        }
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        if ($this->is_destroy) {
            return;
        }
        if ($this->isEfficiencyMonitorEnable) {
            $this->context['execution_time'] = (microtime(true) - $this->start_run_time) * 1000;
            $this->log('Efficiency monitor', Logger::INFO);
        }
        parent::destroy();
        $this->fd = null;
        $this->uid = null;
        $this->client_data = null;
        $this->request = null;
        $this->response = null;
        $this->http_input->reset();
        $this->http_output->reset();
        ControllerFactory::getInstance()->revertController($this);
    }

    /**
     * 获取单元测试捕获的数据
     * @return array
     */
    public function getTestUnitResult()
    {
        $stack = $this->testUnitSendStack;
        $this->testUnitSendStack = [];
        return $stack;
    }

    /**
     * 当控制器方法不存在的时候的默认方法
     */
    public function defaultMethod()
    {
        if ($this->request_type == SwooleMarco::HTTP_REQUEST) {
            $this->redirect404();
        } else {
            throw new SwooleException($this->context['method_name'] . ' method not exist');
        }
    }

    /**
     * sendToUid
     * @param $uid
     * @param $data
     * @throws SwooleException
     */
    protected function sendToUid($uid, $data, $destroy = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is destroy can not send data');
        }
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'sendToUid', 'uid' => $this->uid, 'data' => $data];
        } else {
            get_instance()->sendToUid($uid, $data);
        }
        if ($destroy) {
            $this->destroy();
        }
    }

    /**
     * sendToUids
     * @param $uids
     * @param $data
     * @param $destroy
     * @throws SwooleException
     */
    protected function sendToUids($uids, $data, $destroy = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is destroy can not send data');
        }
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'sendToUids', 'uids' => $uids, 'data' => $data];
        } else {
            get_instance()->sendToUids($uids, $data);
        }
        if ($destroy) {
            $this->destroy();
        }
    }

    /**
     * sendToAll
     * @param $data
     * @param $destroy
     * @throws SwooleException
     */
    protected function sendToAll($data, $destroy = true)
    {
        if ($this->is_destroy) {
            throw new SwooleException('controller is destroy can not send data');
        }
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'sendToAll', 'data' => $data];
        } else {
            get_instance()->sendToAll($data);
        }
        if ($destroy) {
            $this->destroy();
        }
    }

    /**
     * 踢用户
     * @param $uid
     */
    protected function kickUid($uid)
    {
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'kickUid', 'uid' => $uid];
        } else {
            get_instance()->kickUid($uid);
        }
    }

    /**
     * bindUid
     * @param $uid
     * @param bool $isKick
     * @throws \Exception
     */
    protected function bindUid($uid, $isKick = true)
    {
        if (!empty($this->uid)) {
            throw new \Exception("已经绑定过uid");
        }
        $uid = (int)$uid;
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'bindUid', 'fd' => $this->fd, 'uid' => $uid];
        } else {
            get_instance()->bindUid($this->fd, $uid, $isKick);
        }
        $this->uid = $uid;
    }

    /**
     * unBindUid
     */
    protected function unBindUid()
    {
        if (empty($this->uid)) return;
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'unBindUid', 'uid' => $this->uid];
        } else {
            get_instance()->unBindUid($this->uid);
        }
    }

    /**
     * 断开链接
     * @param bool $autoDestroy
     */
    protected function close($autoDestroy = true)
    {
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'close', 'fd' => $this->fd];
        } else {
            get_instance()->close($this->fd);
        }
        if ($autoDestroy) {
            $this->destroy();
        }
    }

    /**
     * Http重定向
     * @param $location
     * @param int $code
     * @throws SwooleException
     * @throws SwooleRedirectException
     */
    protected function redirect($location, $code = 302)
    {
        if ($this->request_type == SwooleMarco::HTTP_REQUEST) {
            throw new SwooleRedirectException($location, $code);
        } else {
            throw new SwooleException('重定向只能在http请求中使用');
        }
    }

    /**
     * 重定向到404
     * @param int $code
     */
    protected function redirect404($code = 302)
    {
        $location = 'http://' . $this->request->header['host'] . "/" . '404';
        $this->redirect($location, $code);
    }

    /**
     * 重定向到控制器，这里的方法名不填前缀
     * @param $controllerName
     * @param $methodName
     * @param int $code
     */
    protected function redirectController($controllerName, $methodName, $code = 302)
    {
        $location = 'http://' . $this->request->header['host'] . "/" . $controllerName . "/" . $methodName;
        $this->redirect($location, $code);
    }

    /**
     * 获取fd的信息
     * @return mixed
     */
    protected function getFdInfo()
    {
        return get_instance()->getFdInfo($this->fd);
    }

    /**
     * @param $topic
     */
    protected function addSub($topic)
    {
        if (empty($this->uid)) return;
        get_instance()->addSub($topic, $this->uid);
    }

    /**
     * @param $topic
     */
    protected function removeSub($topic)
    {
        if (empty($this->uid)) return;
        get_instance()->removeSub($topic, $this->uid);
    }

    /**
     * @param $sub
     * @param $data
     * @param $destroy
     */
    protected function sendPub($sub, $data, $destroy = true)
    {
        get_instance()->pub($sub, $data);
        if ($destroy) {
            $this->destroy();
        }
    }
}