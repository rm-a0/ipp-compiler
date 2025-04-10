<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

use IPP\Student\AST\SOLClass;

class SOLObject
{
    private SOLClass $class;

    /** @var array<mixed> */
    private array $instanceVars = [];

    public function __construct(SOLClass $class)
    {
        $this->class = $class;
    }

    public function getClass() : SOLClass
    {
        return $this->class;
    }

    public function setVar(string $name, mixed $value): void
    {
        $this->instanceVars[$name] = $value;
    }

    public function getVar(string $name): mixed
    {
        return $this->instanceVars[$name] ?? null;
    }
}