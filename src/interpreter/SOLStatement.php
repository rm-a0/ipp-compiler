<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLStatement
{
    private string $var;
    private SOLExpression $expr;

    public function __construct(string $var, SOLExpression $expr)
    {
        $this->var = $var;
        $this->expr = $expr;
    }
}