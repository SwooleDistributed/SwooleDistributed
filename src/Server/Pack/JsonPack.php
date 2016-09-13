<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午2:43
 */

namespace Server\Pack;

class JsonPack implements IPack
{
    public function pack($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE);
    }

    public function unPack($data)
    {
        return json_decode($data);
    }
}