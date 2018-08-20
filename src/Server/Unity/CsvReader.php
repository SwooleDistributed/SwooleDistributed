<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2018/4/23
 * Time: 10:51
 */

namespace Server\Unity;


use app\AppError;
use proto\ProtoMarco;

class CsvReader
{
    public $json;
    public $all;
    /**
     * @var Random[]
     */
    public $probablilityBuilders;
    /**
     * @var array
     */
    public $initChipItem;

    /**
     * CsvReader constructor.
     * @param $dir
     */
    public function __construct($dir)
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && $this->get_extension($file) == "csv") {
                $this->read(realpath($dir . "/" . $file));
            }
            if ($file != '.' && $file != '..' && $this->get_extension($file) == "json") {
                $this->readJson(realpath($dir . "/" . $file));
            }
        }
    }

    /**
     * @param $file
     * @return mixed
     */
    protected function get_extension($file)
    {
        return pathinfo($file, PATHINFO_EXTENSION);
    }

    /**
     * @param $path
     */
    protected function readJson($path)
    {
        $file = file_get_contents($path);
        $name = basename($path, ".json");
        $this->json[$name] = json_decode($file, true);
    }

    /**
     * @param $path
     */
    protected function read($path)
    {
        $file = fopen($path, 'r');
        $name = basename($path, ".csv");
        $lineNum = 0;
        $keys = null;
        while ($line = fgetcsv($file)) {
            $lineNum++;
            if ($lineNum == 1) {//第一行作为说明行
                continue;
            }
            if ($lineNum == 2) {//第二行作为字段
                $keys = $line;
                continue;
            }
            $map = [];
            foreach ($line as $key => $value) {
                $value = trim($value);
                if (is_numeric($value)) {
                    $map[$keys[$key]] = (float)$value;
                } else {
                    $map[$keys[$key]] = $value;
                }
            }
            $this->all[$name][$map['id']] = $map;
            if (!array_key_exists('id', $map)) {
                var_dump($name);
            }
        }
        fclose($file);
    }

    /**
     * @param $csv_name
     * @param $fuc
     * @return array
     */
    public function search($csv_name, $fuc)
    {
        $data = $this->all[$csv_name];
        $result = [];
        foreach ($data as $value) {
            if ($fuc($value)) {
                $result[] = $value;
            }
        }
        return $result;
    }

    /**
     * @param $csv_name
     * @param $fuc
     * @return bool
     * @throws \Exception
     */
    public function searchOne($csv_name, $fuc)
    {
        $data = $this->all[$csv_name];
        foreach ($data as $value) {
            if ($fuc($value)) return $value;
        }
        throw new \Exception("$csv_name 表中查询失败");
    }

    /**
     * 聚合
     * @param $csv_name
     * @param $key
     * @return array
     */
    public function groupBy($csv_name, $key)
    {
        $data = $this->all[$csv_name];
        $types = [];
        foreach ($data as $value) {
            if (!array_key_exists($value[$key], $types)) {
                $types[$value[$key]] = [$value];
            } else {
                $types[$value[$key]][] = $value;
            }
        }
        return $types;
    }

    /**
     * 随机返回个
     * @param $csv_name
     * @param $fuc
     * @return mixed
     */
    public function random($csv_name, $fuc)
    {
        $data = $this->all[$csv_name];
        if ($fuc == null) {
            $result = array_values($data);
        } else {
            $result = [];
            foreach ($data as $value) {
                if ($fuc($value)) {
                    $result[] = $value;
                }
            }
        }
        return array_random($result);
    }

    /**
     * 获取json
     * @param $name
     * @return null
     */
    public function getJson($name)
    {
        return $this->json[$name] ?? null;
    }
}