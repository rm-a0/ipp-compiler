<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLMethod
{
    /**
     * @var mixed Either a PHP callable for native methods, or a SOLBlock for user-defined methods.
     */
    private mixed $native;

    public function __construct(mixed $native)
    {
        $this->native = $native;
    }

    /**
     * Check whether the method is native (i.e., a PHP callable).
     * 
     * @return bool True if the method is a callable, false otherwise.
     */
    public function isNative(): bool
    {
        return is_callable($this->native);
    }

    /**
     * Get the method block if it is user-defined.
     * 
     * @return SOLBlock|null The SOLBlock if available, or null if it's a native method.
     */
    public function getBlock(): ?SOLBlock
    {
        return $this->native instanceof SOLBlock ? $this->native : null;
    }

    /**
     * Get the PHP callable if this method is native.
     * 
     * @return callable|null The callable if available, or null if it's a user-defined method.
     */
    public function getNative(): ?callable
    {
        return is_callable($this->native) ? $this->native : null;
    }
}