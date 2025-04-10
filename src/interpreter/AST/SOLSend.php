<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student\AST;

class SOLSend implements SOLExpression
{
    private string $selector;
    private SOLExpression $target;
    /** @var array<SOLExpression> */
    private array $arguments;

    public function __construct(string $selector, SOLExpression $target, array $arguments)
    {
        $this->selector = $selector;
        $this->target = $target;
        $this->arguments = $arguments;
    }

    public function getSelector(): string
    {
        return $this->selector;
    }

    public function getTarget(): SOLExpression
    {
        return $this->target;
    }

    /** @return array<SOLExpression> */
    public function getArguments(): array
    {
        return $this->arguments;
    }
}