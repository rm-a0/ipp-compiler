<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLSend implements SOLExpression
{
    /**
     * @var SOLExpression The expression evaluating to the receiver object.
     */
    private SOLExpression $receiver;

     /**
     * @var string The selector (method name) being invoked.
     */
    private string $selector;

    /**
     * @var array<SOLExpression> Argument expressions passed to the method.
     */
    private array $args;

    /**
     * Constructor for SOLSend
     * @param string $selector
     * @param SOLExpression $receiver
     * @param array<SOLExpression> $args
     */
    public function __construct(string $selector, SOLExpression $receiver, array $args)
    {
        $this->selector = $selector;
        $this->receiver = $receiver;
        $this->args = $args;
    }

    /**
     * Get the receiver expression.
     * 
     * @return SOLExpression The receiver.
     */
    public function getReceiver(): SOLExpression
    {
        return $this->receiver;
    }

    /**
     * Get the selector (method name).
     * 
     * @return string The method selector.
     */
    public function getSelector(): string
    {
        return $this->selector;
    }

    /**
     * Get the argument expressions.
     * 
     * @return array<SOLExpression> The list of argument expressions.
     */
    public function getArgs(): array
    {
        return $this->args;
    }
}