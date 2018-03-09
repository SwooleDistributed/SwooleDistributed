<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-9-28
 * Time: 下午2:45
 */

namespace Server\Middlewares;

use Server\Components\Middleware\HttpMiddleware;

class NormalHttpMiddleware extends HttpMiddleware
{
    protected static $cache404;

    public function __construct()
    {
        parent::__construct();
        if (NormalHttpMiddleware::$cache404 == null) {
            $template = get_instance()->loader->view('server::404');
            NormalHttpMiddleware::$cache404 = $template;
        }
    }

    public function before_handle()
    {
        list($host) = explode(':', $this->request->header['host'] ?? '');
        $path = $this->request->server['path_info'];
        if ($path == '/404') {
            $this->response->status(400);
            $this->response->header('HTTP/1.1', '404 Not Found');
            $this->response->end(NormalHttpMiddleware::$cache404);
            $this->interrupt();
        }
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($path == "/") {//寻找主页
            //先查看有木有模板
            $render = $this->getRender($host);
            if ($render != null) {
                $this->response->end(get_instance()->loader->view($render));
                $this->interrupt();
                return;
            }
            //再看有没有指定index
            $index = $this->getHostIndex($host);
            if (is_string($index)) {
                $www_path = $this->getHostRoot($host) . $this->getHostIndex($host);
                $result = httpEndFile($www_path, $this->request, $this->response);
                if (!$result) {
                    $this->redirect404();
                } else {
                    $this->interrupt();
                }
            } elseif (is_array($index)) {
                $this->request->server['path_info'] = "/" . implode("/", $index);
            } else {
                $this->redirect404();
            }
        } else if (!empty($extension)) {//有后缀
            $www_path = $this->getHostRoot($host) . $path;
            $result = httpEndFile($www_path, $this->request, $this->response);
            if (!$result) {
                $this->redirect404();
            } else {
                $this->interrupt();
            }
        }
    }

    public function after_handle($path)
    {
        // TODO: Implement after_handle() method.
    }
}