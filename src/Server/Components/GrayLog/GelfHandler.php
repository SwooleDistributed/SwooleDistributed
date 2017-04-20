<?php declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-4-20
 * Time: 下午3:21
 */

namespace Server\Components\GrayLog;
use Gelf\PublisherInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\Formatter\FormatterInterface;

class GelfHandler extends AbstractProcessingHandler
{
    /**
     * @var PublisherInterface|null the publisher object that sends the message to the server
     */
    protected $publisher;

    /**
     * @param PublisherInterface $publisher a publisher object
     * @param int                $level     The minimum logging level at which this handler will be triggered
     * @param bool               $bubble    Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct(PublisherInterface $publisher, $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->publisher = $publisher;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        $this->publisher = null;
    }

    /**
     * {@inheritdoc}
     */
    protected function write(array $record)
    {
        $this->publisher->publish($record['formatted']);
    }

    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter(): FormatterInterface
    {
        return new GelfMessageFormatter();
    }
}
