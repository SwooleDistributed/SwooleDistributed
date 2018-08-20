<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2018/4/20
 * Time: 17:23
 */

namespace Server\Unity;


class Random
{
    const TYPE_LINE_GROW = 0;
    const TYPE_LINE_REDUCE = 1;
    protected $map = [];
    public $randomArr = [];
    protected $baseCount;

    public function __construct($baseCount = 100)
    {
        $this->baseCount = $baseCount;
    }

    /**
     * 放入池中
     * @param $data
     * @param $totalValue
     */
    public function pushArray($data, $totalValue)
    {
        $count = count($data);
        foreach ($data as $one) {
            $this->push($one, $totalValue / $count);
        }
    }

    /**
     * 放入池中
     * @param $data
     * @param $value
     */
    public function push($data, $value)
    {
        $index = count($this->map);
        $this->map[$index] = $data;
        for ($i = 0; $i < $this->baseCount * $value; $i++) {
            $this->randomArr[] = $index;
        }
    }
    /**
     * 放入池中
     * @param $data
     * @param $value
     */
    public function pushCount($data, $value)
    {
        $index = count($this->map);
        $this->map[$index] = $data;
        for ($i = 0; $i < $value; $i++) {
            $this->randomArr[] = $index;
        }
    }

    /**
     * 放入一个范围
     * @param $min
     * @param $max
     * @param $type
     */
    public function pushRange($min, $max, $type)
    {
        switch ($type) {
            case self::TYPE_LINE_GROW:
                for ($i = $min; $i <= $max; $i++) {
                    $this->map[$i] = $i;
                    for ($j = 0; $j <= $i - $min; $j++) {
                        $this->randomArr[] = $i;
                    }
                }
                break;
            case self::TYPE_LINE_REDUCE:
                for ($i = $min; $i <= $max; $i++) {
                    $this->map[$i] = $i;
                    for ($j = 0; $j <= $max - $i; $j++) {
                        $this->randomArr[] = $i;
                    }
                }
                break;
        }
    }

    /**
     * @return mixed
     */
    public function random()
    {
        $index = array_random($this->randomArr);
        return $this->map[$index];
    }
}