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

    function distribute($data);

    function execute($data);

    function pushToPool($client);

    function prepareOne();

    function addTokenCallback($callback);

    function getSync();
}