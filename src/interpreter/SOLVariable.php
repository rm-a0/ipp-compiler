<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLVariable implements SOLExpression
{
    /**
     * @var string The name of the variable being referenced.
     */
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * Get the name of the variable.
     * 
     * @return string The variable name.
     */
    public function getName(): string
    {
        return $this->name;
    }
}