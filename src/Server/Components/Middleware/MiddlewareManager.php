<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-9-28
 * Time: 上午11:25
 */

namespace Server\Components\Middleware;


use Server\CoreBase\SwooleException;
use Server\Memory\Pool;

class MiddlewareManager
{

    /**
     * @param $middlewares
     * @param $context
     * @param $params
     * @param bool $isHttp
     * @return array
     */
    public function create($middlewares, &$context, $params, $isHttp = false)
    {
        $m = [];
        foreach ($middlewares as $middleware) {
            $one = $this->createOne($middleware);
            if (!$isHttp && $one instanceof HttpMiddleware) {
                Pool::getInstance()->push($one);
                continue;
            }
            $one->setContext($context);
            if (is_callable([$one, 'init'])) {
                $one->init(...$params);
            }
            $m[] = $one;
        }
        return $m;
    }

    /**
     * @param $middlewares
     */
    public function destory($middlewares)
    {
        foreach ($middlewares as $middleware) {
            Pool::getInstance()->push($middleware);
        }
    }

    /**
     * @param $middleware_name
     * @return mixed
     * @throws SwooleException
     */
    protected function createOne($middleware_name)
    {
        $middleware = Pool::getInstance()->get($middleware_name);
        return $middleware;
    }

    /**
     * before
     * @param $middlewares
     * @return \Generator
     */
    public function before($middlewares)
    {
        $count = count($middlewares);
        for ($i = 0; $i < $count; $i++) {
            $middlewares[$i]->getProxy()->before_handle();
        }
    }

    /**
     * after
     * @param $middlewares
     * @return \Generator
     */
    public function after($middlewares, $path)
    {
        $count = count($middlewares);
        for ($i = $count - 1; $i >= 0; $i--) {
            $middlewares[$i]->getProxy()->after_handle($path);
        }
    }
}