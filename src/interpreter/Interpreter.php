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

class Interpreter extends AbstractInterpreter
{
    /** @var array<string, SOLClass> */
    private array $classes = [];

    public function execute(): int
    {
        // TODO: Start your code here
        // Hints:
        // $val = $this->input->readString();
        // $this->stdout->writeString("stdout");
        // $this->stderr->writeString("stderr");
        // Check IPP\Core\AbstractInterpreter for predefined I/O objects:
        $dom = $this->loadSource();
        
        $program = $dom->documentElement;
        if ($dom === NULL) {
            return ReturnCode::INVALID_XML_ERROR;
        }

        // Parse XML representation of the program
        $parseResult = $this->parseProgram($dom->documentElement);
        if ($parseResult != ReturnCode::OK) {
            return $parseResult;
        }

        if(!isset($this->classes["Main"])) {
            $this->stderr->writeString("Error: Main class not found\n");
            return ReturnCode::PARSE_MAIN_ERROR;
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

    /**
     * Parses root of the DOM document
     * @return int The appropriate return code
     */
    private function parseProgram(DOMElement $root): int
    {
        // Validate program element
        if ($root->tagName != "program" || $root->getAttribute("language") != "SOL25") {
            $this->stderr->writeString("Invalid XML format: root element must be 'program' with language='SOL25'\n");
            return ReturnCode::INVALID_SOURCE_STRUCTURE_ERROR;
        }

        // Parse all class elements
        foreach ($root->getElementsByTagName("class") as $classNode) {
            $this->parseClass($classNode);
        }

        return ReturnCode::OK;
    }

    /**
     * Parses class element of the DOM document and creates class object
     * @return int The appropriate return code
     */
    private function parseClass(DOMElement $classNode): int
    {
        $name = $classNode->getAttribute("name");
        $parent = $classNode->getAttribute("parent");

        if (empty($name) || empty($parent)) {
            $this->stderr->writeString("Invalid class definition: missing name or parent\n");
            return ReturnCode::INVALID_SOURCE_STRUCTURE_ERROR;
        }

        $class = new SOLClass($name, $parent);

        $this->classes[$name] = $class;
        return ReturnCode::OK;
    }
}
