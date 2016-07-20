<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 上午11:38
 */
/**
 * 获取实例
 * @return \Server\SwooleDistributedServer
 */
function &get_instance()
{
    return \Server\SwooleDistributedServer::get_instance();
}

/**
 * 这个函数防止在 ASCII 字符串中插入空字符
 * remove_invisible_characters('Java\\0script'); Returns: 'Javascript'
 * @param $str
 * @param bool $url_encoded
 * @return mixed
 */
function remove_invisible_characters($str, $url_encoded = TRUE)
{
    $non_displayables = array();

    // every control character except newline (dec 10),
    // carriage return (dec 13) and horizontal tab (dec 09)
    if ($url_encoded)
    {
        $non_displayables[] = '/%0[0-8bcef]/';	// url encoded 00-08, 11, 12, 14, 15
        $non_displayables[] = '/%1[0-9a-f]/';	// url encoded 16-31
    }

    $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';	// 00-08, 11, 12, 14-31, 127

    do
    {
        $str = preg_replace($non_displayables, '', $str, -1, $count);
    }
    while ($count);

    return $str;
}