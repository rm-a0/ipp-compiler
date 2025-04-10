<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

use DOMDocument;
use DOMElement;
use IPP\Core\AbstractInterpreter;
use IPP\Core\ReturnCode;
use IPP\Core\Exception\NotImplementedException;
use IPP\Student\AST;

class Interpreter extends AbstractInterpreter
{
    /** @var array<string, SOLClass> */
    private array $classes = [];

    /** @var array<string, SOLObject> */
    private array $instances = [];

    /** @var array<string, mixed> */
    private array $variables = [];

    public function execute(): int
    {
        $dom = $this->loadSource();
        if ($dom === null) {
            return ReturnCode::INVALID_XML_ERROR;
        }

        // Parse XML representation of the program
        $xmlParser = new XMLParser($this->stderr);
        $parseResult = $xmlParser->parse($dom->documentElement);
        if ($parseResult != ReturnCode::OK) {
            // return $parseResult;
            exit($parseResult);
        }

        // Extract parsed classes from XMLParser
        $classes = $xmlParser->getClasses();

        if(!isset($classes["Main"])) {
            $this->stderr->writeString("Error: Main class not found\n");
            exit(ReturnCode::PARSE_MAIN_ERROR);
        }

        return ReturnCode::OK;
    }

    /**
     * Loads the XML source from file or stdin using the SourceReader.
     * @return ?DOMDocument Loaded DOM document or null
     */
    private function loadSource(): ?DOMDocument
    {
        try {
            $dom = $this->source->getDOMDocument();
            return $dom;
        } catch (\Exception $e) {
            $this->stderr->writeString("Error: failed loading XML: " . $e->getMessage() . "\n");
            return null;
        }
    }
}
