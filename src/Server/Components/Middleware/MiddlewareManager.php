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
     * @return array
     */
    public function create($middlewares, &$context, $params)
    {
        $m = [];
        foreach ($middlewares as $middleware) {
            $one = $this->createOne($middleware);
            $one->setContext($context);
            if (is_callable([$one, 'init'])) {
                call_user_func_array([$one, 'init'], $params);
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
            yield $middlewares[$i]->getProxy()->before_handle();
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
            yield $middlewares[$i]->getProxy()->after_handle($path);
        }
    }
}