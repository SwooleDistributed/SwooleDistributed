<?php

namespace Server\Tasks;

use Server\CoreBase\Task;

/**
 * Class TestCache
 * @package Server\Tasks
 */
class TestCache extends Task
{
    public $map = [];

    public function addMap($value)
    {
        $this->map[] = $value;
        return true;
    }

    public function getAllMap()
    {
        return $this->map;
    }
}