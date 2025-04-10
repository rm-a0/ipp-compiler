<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student\AST;

class SOLLiteral implements SOLExpression
{
    private string $class;
    private string $value;

    public function __construct(string $class, string $value)
    {
        $this->class = $class;
        $this->value = $value;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}

