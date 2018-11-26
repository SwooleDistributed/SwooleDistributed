<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-29
 * Time: 上午11:02
 */

namespace Server\CoreBase;


class HttpInput
{
    /**
     * http request
     * @var \swoole_http_request
     */
    public $request;

    /**
     * @param $request
     */
    public function set($request)
    {
        $this->request = $request;
    }

    /**
     * 重置
     */
    public function reset()
    {
        unset($this->request);
    }

    /**
     * postGet
     * @param $index
     * @param $xss_clean
     * @return string
     */
    public function postGet($index, $xss_clean = true)
    {
        return isset($this->request->post[$index])
            ? $this->post($index, $xss_clean)
            : $this->get($index, $xss_clean);
    }

    /**
     * post
     * @param $index
     * @param $xss_clean
     * @return string
     */
    public function post($index, $xss_clean = true)
    {
        if ($xss_clean) {
            return XssClean::getXssClean()->xss_clean($this->request->post[$index]??'');
        } else {
            return $this->request->post[$index]??'';
        }
    }

    /**
     * get
     * @param $index
     * @param $xss_clean
     * @return string
     */
    public function get($index, $xss_clean = true)
    {
        if ($xss_clean) {
            return XssClean::getXssClean()->xss_clean($this->request->get[$index]??'');
        } else {
            return $this->request->get[$index]??'';
        }
    }

    /**
     * getPost
     * @param $index
     * @param $xss_clean
     * @return string
     */
    public function getPost($index, $xss_clean = true)
    {
        return isset($this->request->get[$index])
            ? $this->get($index, $xss_clean)
            : $this->post($index, $xss_clean);
    }

    /**
     * 获取所有的post
     */
    public function getAllPost()
    {
        return $this->request->post ?? [];
    }

    /**
     * 获取所有的get
     */
    public function getAllGet()
    {
        return $this->request->get ?? [];
    }
    /**
     * 获取所有的post和get
     */
    public function getAllPostGet()
    {
        return array_merge($this->request->post ?? [], $this->request->get ?? []);
    }

    /**
     * @param $index
     * @param bool $xss_clean
     * @return array|bool|string
     */
    public function header($index, $xss_clean = true)
    {
        if ($xss_clean) {
            return XssClean::getXssClean()->xss_clean($this->request->header[$index]??'');
        } else {
            return $this->request->header[$index]??'';
        }
    }

    /**
     * getAllHeader
     * @return array
     */
    public function getAllHeader()
    {
        return $this->request->header;
    }

    /**
     * 获取原始的POST包体
     * @return mixed
     */
    public function getRawContent()
    {
        return $this->request->rawContent();
    }

    /**
     * cookie
     * @param $index
     * @param $xss_clean
     * @return string
     */
    public function cookie($index, $xss_clean = true)
    {
        if ($xss_clean) {
            return XssClean::getXssClean()->xss_clean($this->request->cookie[$index]??'');
        } else {
            return $this->request->cookie[$index]??'';
        }
    }

    /**
     * getRequestHeader
     * @param $index
     * @param $xss_clean
     * @return string
     */
    public function getRequestHeader($index, $xss_clean = true)
    {
        if ($xss_clean) {
            return XssClean::getXssClean()->xss_clean($this->request->header[$index]??'');
        } else {
            return $this->request->header[$index]??'';
        }
    }

    /**
     * 获取Server相关的数据
     * @param $index
     * @param bool $xss_clean
     * @return array|bool|string
     */
    public function server($index, $xss_clean = true)
    {
        if ($xss_clean) {
            return XssClean::getXssClean()->xss_clean($this->request->server[$index]??'');
        } else {
            return $this->request->server[$index]??'';
        }
    }

    /**
     * @return mixed
     */
    public function getRequestMethod()
    {
        return $this->request->server['request_method'];
    }

    /**
     * @return mixed
     */
    public function getRequestUri()
    {
        if (array_key_exists('query_string', $this->request->server)) {
            return $this->request->server['request_uri'] . "?" . $this->request->server['query_string'];
        } else {
            return $this->request->server['request_uri'];
        }
    }

    /**
     * @return mixed
     */
    public function getPathInfo()
    {
        return $this->request->server['path_info'];
    }

    /**
     * 文件上传信息
     * Array
     * (
     *   [name] => facepalm.jpg
     *   [type] => image/jpeg
     *   [tmp_name] => /tmp/swoole.upfile.n3FmFr
     *   [error] => 0
     *   [size] => 15476
     * )
     * @return mixed
     */
    public function getFiles()
    {
        return $this->request->files;
    }
}