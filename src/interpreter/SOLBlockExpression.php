<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLBlockExpression implements SOLExpression
{
    private SOLBlock $block;

    public function __construct(SOLBlock $block)
    {
        $this->block = $block;
    }

    public function getBlock(): SOLBlock
    {
        return $this->block;
    }
}