<?php

namespace Server\Console;

use app\AppServer;
use Reflection;
use ReflectionClass;
use ReflectionMethod;
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
            $io = new SymfonyStyle($input, $output);
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
            $method_name = $io->choice("输入调用的方法名",$this->getMethods(get_class($model)));
            //获得参数
            $result = $this->getFucntionParameterName(get_class($model), $method_name);
            $params = [];
            while (count($result)!=0){
                $p = array_shift($result);
                $params[] = $io->ask("输入参数{$p}的值");
            }
            $result = $model->$method_name(...$params);
            $io->success("结果：");
            print_r($result);
        });
    }

    /**
     * @param $class_name
     * @param $method_name
     * @return array
     * @throws \ReflectionException
     */
    protected function getFucntionParameterName($class_name,$method_name) {
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
     */
    protected function getMethods($class_name)
    {
        $array1 = get_class_methods($class_name);
        if ($parent_class = get_parent_class($class_name)) {
            $array2 = get_class_methods($parent_class);
            $array3 = array_diff($array1, $array2);
        } else {
            $array3 = $array1;
        }
        return $array3;
    }
}