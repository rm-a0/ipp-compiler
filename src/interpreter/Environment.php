<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

/**
 * Represents a variable environment (scope) for the interpreter.
 * 
 * Supports nested environments to allow for variable lookup with lexical scoping.
 */
class Environment
{
    /**
     * Reference to the parent environment (outer scope), or null if this is the global scope.
     * 
     * @var Environment|null
     */
    private ?Environment $parent;

    /**
     * Associative array of variable names to their values in the current scope.
     * 
     * @var array<string, mixed>
     */
    private array $vars = [];


    /**
     * Constructor.
     * 
     * @param Environment|null $parent The parent environment (enclosing scope), if any.
     */
    public function __construct(?Environment $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * Sets a variable in the current environment.
     * 
     * @param string $name  The variable name.
     * @param mixed  $value The value to assign to the variable.
     * 
     * @return void
     */
    public function set(string $name, mixed $value): void
    {
        $this->vars[$name] = $value;
    }

    /**
     * Retrieves the value of a variable by name.
     * 
     * If the variable is not found in the current environment, it recursively
     * checks parent environments (lexical scoping).
     * 
     * @param string $name The variable name.
     * 
     * @return mixed The value of the variable, or null if not found.
     */
    public function get(string $name): mixed
    {
        return $this->vars[$name] ?? $this->parent?->get($name);
    }
}