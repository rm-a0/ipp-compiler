<?php

namespace IPP\Student;

use DOMDocument;
use IPP\Core\AbstractInterpreter;
use IPP\Core\ReturnCode;
use IPP\Core\Exception\NotImplementedException;

class Interpreter extends AbstractInterpreter
{
    public function execute(): int
    {
        // TODO: Start your code here
        // Check IPP\Core\AbstractInterpreter for predefined I/O objects:
        $dom = $this->source->getDOMDocument();
        
        // Check if XML is valid
        if (!$dom instanceof DOMDocument) {
            $this->stderr->writeString("Error: Faled to load XML soruce\n");
            return ReturnCode::INVALID_XML_ERROR;
        }

        $program = $dom->documentElement;

        // $val = $this->input->readString();
        // $this->stdout->writeString("stdout");
        // $this->stderr->writeString("stderr");
        return 0;
    }
}
