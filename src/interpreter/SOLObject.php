<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLObject
{
    private SOLClass $class;
    /** @var array<string, SOLObject> */
    private array $instanceVars = [];
    private mixed $internalValue = null;

    public function __construct(SOLClass $class, mixed $internalValue = null)
    {
        $this->class = $class;
        $this->internalValue = $internalValue;
    }

    public function getClass(): SOLClass
    {
        return $this->class;
    }

    public function getInternalValue(): mixed
    {
        return $this->internalValue;
    }

    public function updateInternalValue(mixed $newValue): void
    {
        $this->internalValue = $newValue;
    }

    public function setVar(string $name, SOLObject $value): void
    {
        $this->instanceVars[$name] = $value;
    }

    public function getVar(string $name): ?SOLObject
    {
        return $this->instanceVars[$name] ?? null;
    }
}