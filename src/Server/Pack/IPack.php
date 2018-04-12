<?php
namespace Server\Pack;
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午2:41
 */
interface IPack
{
    function encode($buffer);

    function decode($buffer);

    function pack($data, $topic = null);

    function unPack($data);

    function getProbufSet();

    function errorHandle(\Throwable $e, $fd);
}