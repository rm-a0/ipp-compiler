<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLMethod
{
    private mixed $native; // callable or SOLBlock

    public function __construct(mixed $native)
    {
        $this->native = $native;
    }

    public function isNative(): bool
    {
        return is_callable($this->native);
    }

    public function getBlock(): ?SOLBlock
    {
        return $this->native instanceof SOLBlock ? $this->native : null;
    }

    public function getNative(): ?callable
    {
        return is_callable($this->native) ? $this->native : null;
    }
}