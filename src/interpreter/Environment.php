<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class Environment
{
    private ?Environment $parent;
    /** @var array<string, mixed> */
    private array $vars = [];

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
        return $this->vars[$name] ?? $this->parent?->get($name);
    }
}