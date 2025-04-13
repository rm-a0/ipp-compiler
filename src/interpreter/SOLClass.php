<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLClass
{ 
    private string $name;
    private ?string $parent;

    /** @var array<string, SOLBlock> */
    private array $methods;

    /**
     * Constructor for SOLClass
     * @param string $name The name of the class
     * @param string $parent The name of the parent class
     */
    public function __construct(string $name, ?string $parent)
    {
        $this->name = $name;
        $this->parent = $parent;
        $this->methods = [];
    }

    /**
     * Adds a method to the class
     * @param string $selector The method selector (name)
     * @param SOLBlock $block The block of code implementing the method
     * @return void
     */
    public function addMethod(string $selector, SOLBlock $block): void
    {
        $this->methods[$selector] = $block;
    }

    /**
     * Getter for method
     * @param string $selector The method selector to look up
     * @return SOLBlock|null The block associated with the selector
     */
    public function getMethod(string $selector): ?SOLBlock
    {
        return $this->methods[$selector] ?? null;
    }

    /**
     * Getter for the class name
     * @return string The name of the class
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Getter for the parent class name
     * @return string The name of the parent class
     */
    public function getParentName(): string
    {
        return $this->parent;
    }
}