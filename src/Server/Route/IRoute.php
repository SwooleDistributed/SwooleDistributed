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

    function errorHandle(\Exception $e, $fd);

    function errorHttpHandle(\Exception $e, $request, $response);

}