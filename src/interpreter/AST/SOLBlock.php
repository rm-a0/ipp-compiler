<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student\AST;

use DOMElement;

class SOLBlock implements SOLExpression
{
    /** @var array<string> */
    private array $params = [];

    /** @var array<array{0: string, 1: DOMElement}> */
    private array $statements = [];

    /**
     * Constructor for SOLBlock
     * @param array<string> $params
     * @param array<array{0: string, 1: DOMElement}> $statements
     */
    public function __construct(array $params, array $statements)
    {
        $this->params = $params;
        $this->statements = $statements;
    }

    /**
     * Getter for parameters
     * @return array<string> The array containing parameters
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /** 
     * Getter for statements
     * @return array<array{0: string, 1: DOMElement}>  The array containing statements
     */
    public function getStatements(): array
    {
        return $this->statements;
    }
}