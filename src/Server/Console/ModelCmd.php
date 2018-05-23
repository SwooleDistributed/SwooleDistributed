<?php

namespace Server\Console;

use app\AppServer;
use ReflectionClass;
use ReflectionMethod;
use Server\Asyn\Redis\RedisLuaManager;
use Server\CoreBase\Child;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2018/4/18
 * Time: 18:01
 */
class ModelCmd extends Command
{
    protected function configure()
    {
        $this->setName('model')->setDescription("Test Model");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        new AppServer();
        go(function () use ($output, $input) {
            get_instance()->initAsynPools(0);
            $redis_pool = get_instance()->getAsynPool("redisPool");
            $redisLuaManager = new RedisLuaManager($redis_pool->getSync());
            $redisLuaManager->registerFile(LUA_DIR);
            $save_map = [];
            $io = new SymfonyStyle($input, $output);
            while (true) {
                while (true) {
                    $model_name = $io->ask("输入调用的Model名称");
                    try {
                        $child = new Child();
                        $model = get_instance()->loader->model($model_name, $child)->getOwn();
                        break;
                    } catch (\Throwable $e) {
                        $io->error($e->getMessage());
                    }
                }
                $method_name = $io->choice("输入调用的方法名", $this->getMethods(get_class($model)));

                $result = $this->getFucntionParameterName(get_class($model), $method_name);
                $params = [];
                while (count($result) != 0) {
                    $p = array_shift($result);
                    while (true) {
                        $pr = $io->ask("输入参数{$p}的值(或者输入保存的变量名以" . '$开头)');
                        if ($this->checkSaveId($pr)) {
                            $pr = $save_map[$pr] ?? null;
                            if ($pr == null) {
                                $io->error("读取变量出错");
                            } else {
                                break;
                            }
                        } else {
                            break;
                        }
                    }
                    $params[] = $pr;
                }
                try {
                    $class = new ReflectionClass(get_class($model));
                    $method = $class->getMethod($method_name);
                    $method->setAccessible(true);
                    $startTime = getMillisecond();
                    if ($method->isPublic()) {
                        $result = $model->$method_name(...$params);
                    } else {
                        $io->note("注意：此函数是非Public函数，通过反射调用如果有协程切换不会返回结果");
                        $result = $method->invoke($model, ...$params);
                    }
                    $useTime = getMillisecond()-$startTime;
                    $io->text("输出结果：");
                    print_r($result);
                    if ($result != null) {
                        while (true) {
                            $save_id = $io->ask("将保存到变量，输入变量名以" . '$开头，或者Ctrl-C结束程序');
                            if(empty($save_id)){
                                $io->note("跳过存储");
                                break;
                            }
                            if (!$this->checkSaveId($save_id)) {
                                $io->error("无效变量名");
                            } else {
                                $save_map[$save_id] = $result;
                                break;
                            }
                        }
                        $efficiency = $io->confirm("是否进行效率测试？",true);
                        if($efficiency){//进行效率测试
                            $startTime = getMillisecond();
                            $ncount = floor(10000/$useTime);
                            $p = $io->createProgressBar($ncount);
                            for ($i=0;$i<$ncount;$i++) {
                                if ($method->isPublic()) {
                                    $model->$method_name(...$params);
                                } else {
                                    $method->invoke($model, ...$params);
                                }
                                $p->setProgress($i+1);
                            }
                            $useTime = getMillisecond()-$startTime;
                            echo "\n";
                            $io->note("平均执行时间：".$useTime/$ncount*1000 ." ns");
                        }
                    }
                } catch (\Throwable $e) {
                    print_r($e->getMessage() . "\n");
                }
            }
        });
    }

    protected function checkSaveId($value)
    {
        if ($value[0] != "$") {
            return false;
        }
        return true;
    }

    /**
     * @param $class_name
     * @param $method_name
     * @return array
     * @throws \ReflectionException
     */
    protected function getFucntionParameterName($class_name, $method_name)
    {
        $class = new ReflectionClass($class_name);
        $method = $class->getMethod($method_name);
        $depend = array();
        foreach ($method->getParameters() as $value) {
            $depend[] = $value->name;
        }
        return $depend;
    }

    /**
     * @param $class_name
     * @return array
     * @throws \ReflectionException
     */
    protected function getMethods($class_name)
    {
        $class = new ReflectionClass($class_name);
        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PRIVATE);
        $array1 = array();
        foreach ($methods as $model) {
            $array1[] = $model->name;
        }
        if ($parent_class = get_parent_class($class_name)) {
            $class = new ReflectionClass($parent_class);
            $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED | ReflectionMethod::IS_PRIVATE);
            $array2 = array();
            foreach ($methods as $model) {
                $array2[] = $model->name;
            }
            $array3 = array_diff($array1, $array2);
        } else {
            $array3 = $array1;
        }
        return $array3;
    }
}