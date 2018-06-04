<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-25
 * Time: 上午11:09
 */

namespace Server\Asyn;


interface IAsynPool
{
    function getAsynName();

    function pushToPool($client);

    function getSync();

    function setName($name);
}