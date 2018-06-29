<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-10-30
 * Time: 下午7:25
 */

namespace Server\Components\Backstage;

use Server\Components\CatCache\CatCacheRpcProxy;
use Server\Components\Cluster\ClusterProcess;
use Server\Components\Process\ProcessManager;
use Server\Components\SDDebug\SDDebug;
use Server\Components\SDHelp\SDHelpProcess;
use Server\CoreBase\Actor;
use Server\CoreBase\ChildProxy;
use Server\CoreBase\Controller;
use Server\Start;
use Server\SwooleMarco;

class Console extends Controller
{
    private $enableXdebug;

    public function __construct(string $proxy = ChildProxy::class)
    {
        parent::__construct($proxy);
        $this->enableXdebug = $this->config->get('backstage.xdebug_enable', false);
    }

    /**
     * onConnect
     * @return void
     * @throws \Exception
     */
    public function back_onConnect()
    {
        $this->bindUid("#bs:" . getNodeName() . $this->fd);
        get_instance()->protect($this->fd);


        $type = $this->http_input->get("type");
        $uid = $this->http_input->get("uid");
        switch ($type) {
            case "channel":
                if (!empty($uid)) {
                    $this->addSub('$SYS_CHANNEL/' . $uid . "/#");
                } else {
                    $this->addSub('$SYS_CHANNEL/#');
                }
                break;
            case "xdebug":
                if (!$this->enableXdebug) {
                    $this->close();
                    return;
                }
                if (Start::getXDebug()) {
                    $this->addSub('$SYS_XDEBUG/#');
                    $files = read_dir_queue(APP_DIR);
                    $sendFiles = [];
                    foreach ($files as $file) {
                        $file = explode("src/app", $file)[1];
                        $list = explode("/", $file);
                        if (count($list) < 3) {
                            continue;
                        }
                        if ($list[1] == "Process" || $list[1] == "AMQPTasks" || $list[1] == "Console" || $list[1] == "Tasks" || $list[1] == "Views") {
                            continue;
                        }
                        $sendFiles[] = $file;
                    }
                    $this->autoSend($sendFiles, '$SYS_XDEBUG/DebugFiles');
                } else {
                    $this->close();
                }
                break;
            case "coverage":
                if (Start::getCoverage()) {
                    $this->addSub('$SYS_COVERAGE/#');
                    $files = read_dir_queue(APP_DIR);
                    $sendFiles = [];
                    foreach ($files as $file) {
                        $file = explode("src/app", $file)[1];
                        $sendFiles[] = $file;
                    }
                    $this->autoSend($sendFiles, '$SYS_COVERAGE/Files');
                } else {
                    $this->close();
                }
                break;
            default:
                $this->addSub('$SYS/#');
        }
    }

    /**
     * onClose
     */
    public function back_onClose()
    {

    }

    /**
     * 设置debug
     * @param $node_name
     * @param $bool
     * @throws \Exception
     */
    public function back_setDebug($node_name, $bool)
    {
        if (get_instance()->isCluster()) {
            ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_setDebug($node_name, $bool);
        } else {
            Start::setDebug($bool);
        }
        $this->autoSend("ok");
    }

    /**
     * reload
     * @param $node_name
     * @throws \Exception
     */
    public function back_reload($node_name)
    {
        if (get_instance()->isCluster()) {
            ProcessManager::getInstance()->getRpcCall(ClusterProcess::class, true)->my_reload($node_name);
        } else {
            get_instance()->server->reload();
        }
        $this->autoSend("ok");
    }

    /**
     * 获取所有的Sub
     * @throws \Exception
     */
    public function back_getAllSub()
    {
        $result = ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->my_getAllSub();
        $this->autoSend($result);
    }

    /**获取uid信息
     * @param $uid
     * @throws \Exception
     */
    public function back_getUidInfo($uid)
    {
        $uidInfo = get_instance()->getUidInfo($uid);
        $this->autoSend($uidInfo);
    }

    /**
     * 获取所有的uid
     * @throws \Exception
     */
    public function back_getAllUids()
    {
        $uids = get_instance()->coroutineGetAllUids();
        $this->autoSend($uids);
    }

    /**
     * 获取sub的uid
     * @param $topic
     * @throws \Exception
     */
    public function back_getSubUid($topic)
    {
        $uids = get_instance()->getSubMembersCoroutine($topic);
        $this->autoSend($uids);
    }

    /**
     * 获取uid所有的订阅
     * @param $uid
     * @throws \Exception
     */
    public function back_getUidTopics($uid)
    {
        $topics = get_instance()->getUidTopicsCoroutine($uid);
        $this->autoSend($topics);
    }

    /**
     * 获取统计信息
     * @param $node_name
     * @param $index
     * @param $num
     * @throws \Exception
     */
    public function back_getStatistics($node_name, $index, $num)
    {
        if (!get_instance()->isCluster() || $node_name == getNodeName()) {
            $map = ProcessManager::getInstance()->getRpcCall(SDHelpProcess::class)->getStatistics($index, $num);
        } else {
            $map = ProcessManager::getInstance()->getRpcCall(ClusterProcess::class)->my_getStatistics($node_name, $index, $num);
        }
        $this->autoSend($map);
    }

    /**
     * 获取CatCache信息
     * @param $path
     * @throws \Exception
     */
    public function back_getCatCacheKeys($path)
    {
        $result = CatCacheRpcProxy::getRpc()->getKeys($path);
        $this->autoSend($result);
    }

    /**
     * 获取CatCache信息
     * @param $path
     * @throws \Exception
     */
    public function back_getCatCacheValue($path)
    {
        $result = CatCacheRpcProxy::getRpc()[$path];
        $this->autoSend($result);
    }

    /**
     * 删除CatCache信息
     * @param $path
     * @throws \Exception
     */
    public function back_delCatCache($path)
    {
        unset(CatCacheRpcProxy::getRpc()[$path]);
        $this->autoSend("ok");
    }

    /**
     * 获取Actor信息
     * @param $name
     * @throws \Exception
     */
    public function back_getActorInfo($name)
    {
        $result = CatCacheRpcProxy::getRpc()["@Actor.$name"];
        $this->autoSend($result);
    }

    /**
     * 销毁Actor
     * @param $name
     * @throws \Exception
     */
    public function back_destroyActor($name)
    {
        Actor::destroyActor($name);
        $this->autoSend("ok");
    }

    /**
     * 销毁全部Actor
     * @throws \Exception
     */
    public function back_destroyAllActor()
    {
        Actor::destroyAllActor();
        $this->autoSend("ok");
    }

    /**
     * @param $filedebugs
     * @throws \Exception
     */
    public function back_debugBreak($filedebugs)
    {
        SDDebug::debugFile($filedebugs);
        $this->autoSend("ok", '$SYS_XDEBUG/StartBreak');
    }

    /**
     * @throws \Exception
     */
    public function back_nextBreak()
    {
        SDDebug::nextBreak();
        $this->autoSend("ok");
    }

    /**
     * @throws \Exception
     */
    public function back_rollBreak()
    {
        Start::cleanXDebugLock();
        $this->autoSend("ok", '$SYS_XDEBUG/RollBreak');
    }

    /**
     * @throws \Exception
     */
    public function back_stopBreak()
    {
        SDDebug::stopDebug();
        $this->autoSend("ok", '$SYS_XDEBUG/StopBreak');
    }

    /**
     * @param $file
     * @throws \Exception
     */
    public function back_getAppFile($file)
    {
        if ($this->request_type == SwooleMarco::HTTP_REQUEST) {
            throw new \Exception("不允许http访问此接口");
        }
        if (is_file(APP_DIR . $file)) {
            $src = file_get_contents(APP_DIR . $file);
            $this->autoSend(['file' => $file, "text" => explode("\n", $src)], '$SYS_XDEBUG/DebugFile');
        }
    }

    /**
     * @param $file
     * @throws \Exception
     */
    public function back_getFileCoverage($file)
    {
        if ($this->request_type == SwooleMarco::HTTP_REQUEST) {
            throw new \Exception("不允许http访问此接口");
        }
        if (is_file(APP_DIR . $file)) {
            $src = file_get_contents(APP_DIR . $file);
            $result = $this->redis->zScan(SwooleMarco::CodeCoverage, 0, "$file:*", 10000000)['data'];
            $data['file'] = $file;
            $data['lines'] = [];
            $line_srcs = explode("\n", $src);
            foreach ($line_srcs as $key => $value) {
                $line['text'] = $value;
                $_line = $key+1;
                $line['count'] = $result["$file:$_line"] ?? 0;
                $data['lines'][] = $line;
            }
            $this->autoSend($data, '$SYS_COVERAGE/File');
        }
    }

    /**
     * @throws \Exception
     */
    public function back_getCoverageScore()
    {
        $result = $this->redis->zRevRangeByScore(SwooleMarco::CodeCoverage, "+inf", "-inf", ['withscores' => true, 'limit' => [0,2000]]);
        $data = [];
        foreach ($result as $key=>$value){
            $data[] = ['text'=>$key,'count'=>$value];
        }
        $this->autoSend($data, '$SYS_COVERAGE/Score');
    }

    /**
     * @param $data
     * @param null $topic
     * @throws \Exception
     */
    protected function autoSend($data, $topic = null)
    {
        switch ($this->request_type) {
            case SwooleMarco::TCP_REQUEST:
                if (empty($topic)) {
                    $this->send($data);
                } else {
                    get_instance()->send($this->fd, $data, true, $topic);
                }
                break;
            case SwooleMarco::HTTP_REQUEST:
                if (is_array($data) || is_object($data)) {
                    $output = json_encode($data, JSON_UNESCAPED_UNICODE);
                } else {
                    $output = $data;
                }
                $this->http_output->setHeader("Content-Type", "text/html;charset=utf-8");
                $this->http_output->setHeader("Access-Control-Allow-Origin", "*");
                $this->http_output->end($output);
                break;
        }
    }
}