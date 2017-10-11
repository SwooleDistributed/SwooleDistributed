<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-9-28
 * Time: 下午2:49
 */

namespace Server\Components\Middleware;

abstract class HttpMiddleware extends Middleware
{
    protected $request;
    protected $response;

    public function init($request, $response)
    {
        $this->response = $response;
        $this->request = $request;
    }

    /**
     * 获得host对应的根目录
     * @param $host
     * @return string
     */
    public function getHostRoot($host)
    {
        $root_path = $this->config['http']['root'][$host]['root'] ?? '';
        if (empty($root_path)) {
            $root_path = $this->config['http']['root']['default']['root'] ?? '';
        }
        if (!empty($root_path)) {
            $root_path = WWW_DIR . "/$root_path/";
        } else {
            $root_path = WWW_DIR . "/";
        }
        return $root_path;
    }

    /**
     * 返回host对应的默认文件
     * @param $host
     * @return mixed|null
     */
    public function getHostIndex($host)
    {
        $index = $this->config['http']['root'][$host]['index'] ?? $this->config['http']['root']['default']['index'] ?? 'index.html';
        return $index;
    }

    public function redirect404()
    {
        $this->response->status(302);
        $location = 'http://' . $this->request->header['host'] . "/" . '404';
        $this->response->header('Location', $location);
        $this->response->end('');
        throw new \Exception('interrupt');
    }
}