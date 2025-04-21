<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

class SOLBlockExpression implements SOLExpression
{
    /** 
     * @var SOLBlock The block that this expression wraps.
     */
    private SOLBlock $block;

    public function __construct(SOLBlock $block)
    {
        $this->block = $block;
    }

    /**
     * Returns the block contained in this expression.
     * 
     * @return SOLBlock The block wrapped by this expression.
     */
    public function getBlock(): SOLBlock
    {
        return $this->block;
    }
}