<?php
namespace Server\Route;
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午3:09
 */
interface IRoute
{
    function handleClientData($data);

    function handleClientRequest($request);

    function getControllerName();

    function getMethodName();

    function getParams();

    function getPath();

    function errorHandle(\Throwable $e, $fd);

    function errorHttpHandle(\Throwable $e, $request, $response);

}