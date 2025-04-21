<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLClass
{
    /**
     * @var string The name of the class.
     */
    private string $name;

    /**
     * @var string|null The name of the parent class, if any.
     */
    private ?string $parentName;

    /**
     * @var array<string, SOLMethod> An associative array of method selectors to method implementations.
     */
    private array $methods = [];

    public function __construct(string $name, ?string $parentName)
    {
        $this->name = $name;
        $this->parentName = $parentName;
    }

    /**
     * Adds a method to the class.
     * 
     * @param string $selector The name (selector) used to invoke the method.
     * @param SOLMethod $method The method to add.
     */
    public function addMethod(string $selector, SOLMethod $method): void
    {
        $this->methods[$selector] = $method;
    }

    /**
     * Retrieves all methods defined in the class.
     * 
     * @return array<string, SOLMethod> The array of method selectors to methods.
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Retrieves a method by its selector.
     * 
     * @param string $selector The name used to call the method.
     * @return SOLMethod|null The corresponding method or null if not found.
     */
    public function getMethod(string $selector): ?SOLMethod
    {
        return $this->methods[$selector] ?? null;
    }

    /**
     * Gets the class name.
     * 
     * @return string The name of the class.
     */
    public function getName(): string
    {
        return $this->name;
    }

     /**
     * Gets the parent class name.
     * 
     * @return string|null The name of the parent class or null if there is none.
     */
    public function getParentName(): ?string
    {
        return $this->parentName;
    }
}