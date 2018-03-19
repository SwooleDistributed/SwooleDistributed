<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 18-3-19
 * Time: 下午3:16
 */

namespace Server\Components\log;


use Monolog\Formatter\JsonFormatter;

class SDJsonFormatter extends JsonFormatter
{
    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $context = $record['context'];
        foreach ($context as $key => $value) {
            $record['cxt_' . $key] = $value;
        }
        $extra = $record['extra'];
        foreach ($extra as $key => $value) {
            $record['ex_' . $key] = $value;
        }
        unset($record['datetime']);
        unset($record['context']);
        unset($record['extra']);
        return $this->toJson($this->normalize($record), true) . ($this->appendNewline ? "\n" : '');
    }
}