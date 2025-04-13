<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLSend implements SOLExpression
{
    private SOLExpression $receiver;
    private string $selector;
    /** @var array<SOLExpression> */
    private array $args;

    public function __construct(string $selector, SOLExpression $receiver, array $args)
    {
        $this->selector = $selector;
        $this->receiver = $receiver;
        $this->args = $args;
    }

    public function getReceiver(): SOLExpression
    {
        return $this->receiver;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getArgs(): array
    {
        return $this->args;
    }
}