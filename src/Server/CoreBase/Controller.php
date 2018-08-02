<?php

namespace Server\CoreBase;

use Server\Asyn\Mysql\Miner;
use Server\Models\Error;
use Server\Start;
use Server\SwooleMarco;
use Throwable;

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
     * Http是否已经end
     * @var bool
     */
    protected $isEnd;
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
     * @var Miner
     */
    public $db;

    /**
     * @var \Redis
     */
    protected $redis;

    /**
     * @var bool
     */
    protected $isEnableError;
    /**
     * @var bool
     */
    protected $isErrorHttpShow;
    /**
     * @var Error
     */
    protected $Error;

    /**
     * Controller constructor.
     * @param string $proxy
     */
    public function __construct($proxy = ChildProxy::class)
    {
        parent::__construct($proxy);
        $this->http_input = new HttpInput();
        $this->http_output = new HttpOutput($this);
        $this->isEnableError = $this->config->get('error.enable');
        $this->isErrorHttpShow = $this->config->get('error.http_show',true);
        $this->root = $this;
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
     * @return void
     * @throws \Exception
     * @throws Throwable
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
     * @return void
     * @throws \Exception
     * @throws Throwable
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
        $this->fd = $request->fd;
        $this->execute($controller_name, $method_name, $params);
    }

    /**
     * @param $controller_name
     * @param $method_name
     * @param $params
     * @return void
     * @throws \Exception
     * @throws Throwable
     */
    /**
     * @param $controller_name
     * @param $method_name
     * @param $params
     * @return void
     * @throws \Exception
     * @throws Throwable
     */
    protected function execute($controller_name, $method_name, $params)
    {
        if (!is_callable([$this, $method_name])) {
            $this->context['raw_method_name'] = "$controller_name:$method_name";
            $method_name = 'defaultMethod';
        }
        try {
            $this->initialization($controller_name, $method_name);
        } catch (Throwable $e) {
            get_instance()->onPhpTick($e);
            $this->getProxy()->onExceptionHandle($e);
            $this->destroy();
            return;
        }
        try {
            if ($params == null) {
                $this->getProxy()->$method_name();
            } else {
                $params = array_values($params);
                $this->getProxy()->$method_name(...$params);
            }
        } catch (Throwable $e) {
            get_instance()->onPhpTick($e);
            $this->getProxy()->onExceptionHandle($e);
        }
        $this->destroy();
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
            $this->context['request_id'] = $this->rpc_request_id;
        } else {
            $this->context['request_id'] = time() . crc32($controller_name . $method_name . getTickTime() . rand(1, 10000000));
        }
        $this->context['controller_name'] = $controller_name;
        $this->context['method_name'] = "$controller_name::$method_name";
        $this->context['ip'] = $this->getFdInfo()['remote_ip'];
        if (!empty($this->uid)) {
            $this->context['uid'] = $this->uid;
        }
        $this->db = $this->loader->mysql("mysqlPool",$this);
        $this->redis = $this->loader->redis("redisPool",$this);
        if ($this->isEnableError) {
            $this->Error = $this->loader->model(Error::class, $this);
        }
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
     * @param Throwable $e
     * @param callable $handle
     */
    public function onExceptionHandle(\Throwable $e, $handle = null)
    {
        //必须的代码
        if ($e instanceof SwooleRedirectException) {
            $this->http_output->setStatusHeader($e->getCode());
            $this->http_output->setHeader('Location', $e->getMessage());
            $this->http_output->end('end');
            return;
        }
        //中断信号
        if ($e instanceof SwooleInterruptException) {
            return;
        }
        if ($e instanceof SwooleException) {
            secho("EX", "--------------------------[报错指南]----------------------------" . date("Y-m-d h:i:s"));
            secho("EX", "异常消息：" . $e->getMessage());
            print_context($this->getContext());
            secho("EX", "--------------------------------------------------------------");
        }
        $this->context['error_message'] = $e->getMessage();
        //如果是HTTP传递request过去
        if ($this->request_type == SwooleMarco::HTTP_REQUEST) {
            $e->request = $this->request;
        }
        //生成错误数据页面
        $error_data = get_instance()->getWhoops()->handleException($e);
        if ($this->isEnableError) {
            try {
                $this->Error->push($e->getMessage(),$error_data);
            } catch (Throwable $e) {
            }
        }

        //可以重写的代码
        if ($handle == null) {
            switch ($this->request_type) {
                case SwooleMarco::HTTP_REQUEST:
                    $this->http_output->setStatusHeader(500);
                    if($this->isErrorHttpShow) {
                        $this->http_output->end($error_data);
                    }else{
                        $this->http_output->end($e->getMessage());
                    }
                    break;
                case SwooleMarco::TCP_REQUEST:
                    try {
                        $this->send($e->getMessage());
                    } catch (\Exception $e) {
                    }
                    break;
            }
        } else {
            sd_call_user_func($handle, $e);
        }
    }

    /**
     * 向当前客户端发送消息
     * @param $data
     * @throws \Exception
     */
    protected function send($data)
    {
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
    }

    /**
     * 销毁
     */
    public function destroy()
    {
        if ($this->is_destroy) {
            return;
        }
        parent::destroy();
        if ($this->request_type == SwooleMarco::HTTP_REQUEST) {
            $this->http_output->end('');
        }
        $this->isEnd = false;
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
     * @throws SwooleException
     */
    public function defaultMethod()
    {
        if ($this->request_type == SwooleMarco::HTTP_REQUEST) {
            $this->http_output->setStatusHeader(302);
            $this->http_output->end($this->loader->view("server::404"));
        } else {
            throw new SwooleException($this->context['raw_method_name'] . ' method not exist');
        }
    }

    /**
     * sendToUid
     * @param $uid
     * @param $data
     * @throws \Exception
     */
    protected function sendToUid($uid, $data)
    {
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'sendToUid', 'uid' => $this->uid, 'data' => $data];
        } else {
            get_instance()->sendToUid($uid, $data);
        }
    }

    /**
     * sendToUids
     * @param $uids
     * @param $data
     * @throws SwooleException
     */
    protected function sendToUids($uids, $data)
    {
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'sendToUids', 'uids' => $uids, 'data' => $data];
        } else {
            get_instance()->sendToUids($uids, $data);
        }
    }

    /**
     * sendToAll
     * @param $data
     * @throws SwooleException
     */
    protected function sendToAll($data)
    {
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'sendToAll', 'data' => $data];
        } else {
            get_instance()->sendToAll($data);
        }
    }

    /**
     * sendToAllFd
     * @param $data
     * @throws SwooleException
     */
    protected function sendToAllFd($data)
    {
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'sendToAllFd', 'data' => $data];
        } else {
            get_instance()->sendToAllFd($data);
        }
    }

    /**
     * 踢用户
     * @param $uid
     * @throws \Exception
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
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'bindUid', 'fd' => $this->fd, 'uid' => $uid];
        } else {
            get_instance()->bindUid($this->fd, $uid, $isKick);
        }
        $this->uid = $uid;
    }

    /**
     * unBindUid
     * @throws \Server\Asyn\MQTT\Exception
     */
    protected function unBindUid()
    {
        if (empty($this->uid)) return;
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'unBindUid', 'uid' => $this->uid];
        } else {
            get_instance()->unBindUid($this->uid, $this->fd);
        }
    }

    /**
     * 断开链接
     */
    protected function close()
    {
        if (Start::$testUnity) {
            $this->testUnitSendStack[] = ['action' => 'close', 'fd' => $this->fd];
        } else {
            get_instance()->close($this->fd);
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
     * @throws SwooleException
     * @throws SwooleRedirectException
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
     * @throws SwooleException
     * @throws SwooleRedirectException
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
     * @throws \Exception
     */
    protected function addSub($topic)
    {
        if (empty($this->uid)) {
            throw new \Exception("必须binduid才能使用sub");
        }
        get_instance()->addSub($topic, $this->uid);
    }

    /**
     * @param $topic
     * @throws \Exception
     */
    protected function removeSub($topic)
    {
        if (empty($this->uid)) return;
        get_instance()->removeSub($topic, $this->uid);
    }

    /**
     * 发布
     * @param $topic
     * @param $data
     * @param array $excludeUids 需要排除的uids
     * @throws \Server\Asyn\MQTT\Exception
     */
    protected function sendPub($topic, $data, $excludeUids = [])
    {
        get_instance()->pub($topic, $data, $excludeUids);
    }

    /**
     * 中断
     * @throws SwooleInterruptException
     */
    public function interrupt()
    {
        if ($this->request_type == SwooleMarco::HTTP_REQUEST) {
            $this->http_output->end("");
        }
        throw new SwooleInterruptException('interrupt');
    }

    /**
     * @return bool
     */
    public function canEnd()
    {
        return !$this->isEnd;
    }

    /**
     * endOver
     */
    public function endOver()
    {
        $this->isEnd = true;
    }
}