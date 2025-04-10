<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student\AST;

class SOLVariable implements SOLExpression
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}