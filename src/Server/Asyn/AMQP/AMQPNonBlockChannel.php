<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/24
 * Time: 15:15
 */

namespace Server\Asyn\AMQP;


use PhpAmqpLib\Channel\AMQPChannel;

class AMQPNonBlockChannel extends AMQPChannel
{
    public function next_frame_non_blocking()
    {
        $this->debug->debug_msg('waiting for a new frame');

        if (!empty($this->frame_queue)) {
            return array_shift($this->frame_queue);
        }
        return null;
    }
    /**
     * @param null $allowed_methods
     * @return mixed|void
     */
    public function waitNonBlocking($allowed_methods = null)
    {
        $this->debug->debug_allowed_methods($allowed_methods);

        $deferred = $this->process_deferred_methods($allowed_methods);
        if ($deferred['dispatch'] === true) {
            return $this->dispatch_deferred_method($deferred['queued_method']);
        }
        $result = $this->next_frame_non_blocking();
        if(empty($result)) return;
        list($frame_type, $payload) = $result;
        $this->validate_method_frame($frame_type);
        $this->validate_frame_payload($payload);

        $method_sig = $this->build_method_signature($payload);
        $args = $this->extract_args($payload);

        $this->debug->debug_method_signature('> %s', $method_sig);

        $amqpMessage = $this->maybe_wait_for_content($method_sig);

        if ($this->should_dispatch_method($allowed_methods, $method_sig)) {
            return $this->dispatch($method_sig, $args, $amqpMessage);
        }

        // Wasn't what we were looking for? save it for later
        $this->debug->debug_method_signature('Queueing for later: %s', $method_sig);
        $this->method_queue[] = array($method_sig, $args, $amqpMessage);
    }

}