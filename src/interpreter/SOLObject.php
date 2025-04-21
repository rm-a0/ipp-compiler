<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLObject
{
    /**
     * @var SOLClass The class this object is an instance of.
     */
    private SOLClass $class;

    /**
     * @var array<string, SOLObject> Map of instance variable names to their values.
     */
    private array $instanceVars = [];

    /**
     * @var mixed An optional internal primitive value (e.g., for literals).
     */
    private mixed $internalValue = null;

    public function __construct(SOLClass $class, mixed $internalValue = null)
    {
        $this->class = $class;
        $this->internalValue = $internalValue;
    }

    /**
     * Get the class of the object.
     * 
     * @return SOLClass The class of the object.
     */
    public function getClass(): SOLClass
    {
        return $this->class;
    }

    /**
     * Get the internal primitive value, if any.
     * 
     * @return mixed The internal value.
     */
    public function getInternalValue(): mixed
    {
        return $this->internalValue;
    }

    /**
     * Update the internal primitive value.
     * 
     * @param mixed $newValue The new internal value.
     */
    public function updateInternalValue(mixed $newValue): void
    {
        $this->internalValue = $newValue;
    }

    /**
     * Set an instance variable.
     * 
     * @param string $name The name of the variable.
     * @param SOLObject $value The value to assign to the variable.
     */
    public function setVar(string $name, SOLObject $value): void
    {
        $this->instanceVars[$name] = $value;
    }

    /**
     * Get the value of an instance variable.
     * 
     * @param string $name The name of the variable.
     * @return SOLObject|null The value of the variable, or null if not set.
     */
    public function getVar(string $name): ?SOLObject
    {
        return $this->instanceVars[$name] ?? null;
    }
}