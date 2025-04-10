<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class Environment
{
    /** @var array<string, mixed> */
    private array $vars = [];
    private ?Environment $parent;

    public function __construct(?Environment $parent = null)
    {
        $this->parent = $parent;
    }

    public function set(string $name, mixed $value): void
    {
        $this->vars[$name] = $value;
    }

    public function get(string $name): mixed
    {
        if (array_key_exists($name, $this->vars)) {
            return $this->vars[$name];
        }
        if ($this->parent !== null) {
            return $this->parent->get($name);
        }
        return null;
    }
}