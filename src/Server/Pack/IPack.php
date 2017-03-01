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
    function pack($data);

    function unPack($data);
}