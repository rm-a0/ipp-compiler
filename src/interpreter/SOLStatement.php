<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLStatement
{
    /**
     * @var string The name of the variable to assign to.
     */
    private string $var;

    /**
     * @var SOLExpression The expression whose result is assigned.
     */
    private SOLExpression $expr;

    public function __construct(string $var, SOLExpression $expr)
    {
        $this->var = $var;
        $this->expr = $expr;
    }

    /**
     * Get the name of the variable being assigned.
     * 
     * @return string The variable name.
     */
    public function getVarName(): string
    {
        return $this->var;
    }

    /**
     * Get the expression being assigned to the variable.
     * 
     * @return SOLExpression The expression.
     */
    public function getExpr(): SOLExpression
    {
        return $this->expr; 
    }
}