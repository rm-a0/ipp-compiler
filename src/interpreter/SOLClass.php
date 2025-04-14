<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLClass
{
    private string $name;
    private ?string $parentName;

    /** @var array<string, SOLMethod> */
    private array $methods = [];

    public function __construct(string $name, ?string $parentName)
    {
        $this->name = $name;
        $this->parentName = $parentName;
    }

    public function addMethod(string $selector, SOLMethod $method): void
    {
        $this->methods[$selector] = $method;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getMethod($selector): ?SOLMethod
    {
        return $this->methods[$selector] ?? null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParentName(): ?string
    {
        return $this->parentName;
    }
}