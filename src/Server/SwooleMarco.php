<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-20
 * Time: 下午1:39
 */

namespace Server;


class SwooleMarco
{
    /**
     * 获取服务器ID
     */
    const MSG_TYPE_USID = -1;
    /**
     * 心跳
     */
    const MSG_TYPE_HEART = -2;
    /**
     * 发送消息
     */
    const MSG_TYPE_SEND = 0;
    /**
     * 批量发消息
     */
    const MSG_TYPE_SEND_BATCH = 1;
    /**
     * 全服广播
     */
    const MSG_TYPE_SEND_ALL = 2;
    /**
     * 发送给群
     */
    const MSG_TYPE_SEND_GROUP = 3;
    /**
     * 踢uid下线
     */
    const MSG_TYPE_KICK_UID = 4;
    /**
     * 添加server
     */
    const ADD_SERVER = 3003;

    /**
     * task任务
     */
    const SERVER_TYPE_TASK = 500;

    /**
     * 移除dispatch
     */
    const REMOVE_DISPATCH_CLIENT = 2002;

    /**
     * consul 服务变更
     */
    const CONSUL_SERVICES_CHANGE = 8500;
    /**
     * consul Leader变更
     */
    const CONSUL_SERVICES_LEADER_CHANGE = 8600;
    /**
     * consul_session
     */
    const CONSUL_SERVICES_SESSION = 8700;
    /**
     * redis uid和全局usid映射表的hashkey
     * @var string
     */
    const redis_uid_usid_hash_name = '@server_uid_usid';

    /**
     *  redis group前缀
     */
    const redis_group_hash_name_prefix = '@server_group_';

    /**
     *  redis groups
     */
    const redis_groups_hash_name = '@server_groups';

    /**
     * TCP请求
     */
    const TCP_REQUEST = 'tcp_request';
    /**
     * HTTP请求
     */
    const HTTP_REQUEST = 'http_request';
}