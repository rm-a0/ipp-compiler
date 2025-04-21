<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLLiteral implements SOLExpression
{
    /**
     * @var string The type/class of the literal (e.g., "Int", "String", "Bool").
     */
    private string $class;

    /**
     * @var string The literal value, stored as a string regardless of original type.
     */
    private string $value;

    public function __construct(string $class, string $value)
    {
        $this->class = $class;
        $this->value = $value;
    }

    /**
     * Gets the type/class of the literal.
     * 
     * @return string The literal's class/type.
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * Gets the value of the literal.
     * 
     * @return string The literal's value as a string.
     */
    public function getValue(): string
    {
        return $this->value;
    }
}