<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午3:11
 */

namespace Server\Route;


use Server\Components\Backstage\Console;
use Server\CoreBase\SwooleException;

class ConsoleRoute implements IRoute
{
    private $client_data;

    public function __construct()
    {
        $this->client_data = new \stdClass();
    }

    /**
     * 设置反序列化后的数据 Object
     * @param $data
     * @return \stdClass
     * @throws SwooleException
     */
    public function handleClientData($data)
    {
        $data = get_object_vars($data);
        $varArray = array_keys($data);
        $this->client_data->m = $varArray[0];
        $this->client_data->p = $data[$varArray[0]];
        return $this->client_data;
    }

    /**
     * 处理http request
     * @param $request
     */
    public function handleClientRequest($request)
    {
        $this->client_data->m = substr($request->server['path_info'], 1);
        $this->client_data->p = $request->get;
    }

    /**
     * 获取控制器名称
     * @return string
     */
    public function getControllerName()
    {
        return Console::class;
    }

    /**
     * 获取方法名称
     * @return string
     */
    public function getMethodName()
    {
        return $this->client_data->m;
    }

    public function getPath()
    {
        return $this->getControllerName() . "/" . $this->getMethodName();
    }

    public function getParams()
    {
        return $this->client_data->p ?? null;
    }

    public function errorHandle(\Throwable $e, $fd)
    {
        get_instance()->send($fd, "Error:" . $e->getMessage(), true);
        get_instance()->close($fd);
    }

    public function errorHttpHandle(\Throwable $e, $request, $response)
    {
        //重定向到404
        $response->status(302);
        $location = 'http://' . $request->header['host'] . "/" . '404';
        $response->header('Location', $location);
        $response->end('');
    }
}