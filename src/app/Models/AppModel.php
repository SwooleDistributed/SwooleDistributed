<?php
/**
 * Created by PhpStorm.
 * User: tmtbe
 * Date: 16-7-15
 * Time: 下午1:44
 */

namespace app\Models;


use Server\CoreBase\Model;
use Server\Tasks\TestTask;

class AppModel extends Model
{
    public function test(){
        return 123456;
    }
}