<?php

/**
 * IPP - PHP Project Interpreter
 * @author Michal Repcik
 */

namespace IPP\Student;

use DOMDocument;
use DOMElement;
use IPP\Core\Interface\OutputWriter;
use IPP\Core\ReturnCode;

class XMLParser
{
    /** @var array<string, SOLClass> */
    private array $classes = [];
    private OutputWriter $stderr;

    public function __construct(OutputWriter $stderr)
    {
        $this->stderr = $stderr;
    }

    /**
     * Parses root of the DOM document
     * @return int The appropriate return code
     */
    public function parse(DOMElement $root): int
    {
        // Validate program element
        if ($root->tagName != "program" || $root->getAttribute("language") != "SOL25") {
            $this->stderr->writeString("Invalid XML format: root element must be 'program' with language='SOL25'\n");
            return ReturnCode::INVALID_SOURCE_STRUCTURE_ERROR;
        }

        // Parse all class elements
        foreach ($root->getElementsByTagName("class") as $classNode) {
            $result = $this->parseClass($classNode);
            if ($result != ReturnCode::OK) {
                return $result;
            }
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

        // Append all methods to class methods
        foreach ($classNode->getElementsByTagName("method") as $methodNode) {
            // Extract selector
            $selector = $methodNode->getAttribute("selector");
            if (empty($selector)) {
                $this->stderr->writeString("Invalid method in class $name: missing selector\n");
                return ReturnCode::INVALID_SOURCE_STRUCTURE_ERROR;
            }

            // Get block element
            $blockNode = $methodNode->getElementsByTagName("block")->item(0);
            if (!$blockNode instanceof DOMElement) {
                $this->stderr->writeString("Invalid method $selector in class $name: missing block\n");
                return ReturnCode::INVALID_SOURCE_STRUCTURE_ERROR;
            }

            // Parse block element
            $block = $this->parseBlock($blockNode);
            if ($block === null) {
                $this->stderr->writeString("Invalid method $selector in class $name: missing block\n");
                return ReturnCode::INVALID_SOURCE_STRUCTURE_ERROR;
            }

            $class->addMethod($selector, $block);


        }
        $this->classes[$name] = $class;
        return ReturnCode::OK;
    }

    /**
     * Parses block element of the DOM document and creates block object
     * @return ?SOLBlock Block object or null
     */
    private function parseBlock(DOMElement $blockNode): ?SOLBlock
    {
        return null;
    }

    /**
     * Getter for the class array
     * @return array<string, SOLClass> The array containing parsed classes
     */
    public function getClasses(): array
    {
        return $this->classes;
    }
}