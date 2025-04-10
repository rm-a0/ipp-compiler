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
    /** @var array<SOLClass> */
    private array $classes = [];

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
        $this->classes = array_merge($this->classes, $xmlParser->getClasses());
        if (!isset($this->classes["Main"])) {
            $this->stderr->writeString("Error: Main class not found\n");
            exit(ReturnCode::PARSE_MAIN_ERROR);
        }

        $mainClass = $this->classes["Main"];
        $runBlock = $mainClass->getMethod("run");
        if ($runBlock === null) {
            $this->stderr->writeString("Error: run method not found in Main\n");
            return ReturnCode::PARSE_MAIN_ERROR;
        }

        // Create instance of main class
        $mainObj = new SOLObject($mainClass);
        $env = new Environment();
        $env->set("self", $mainObj);
        $finalValue = $this->interpretBlock($runBlock, $mainObj, [], $env);
        $this->stdout->writeString("Final value: '$finalValue'\n");

        return ReturnCode::OK;
    }

    private function interpretBlock(SOLBlock $block, SOLObject $target, array $args, Environment $env): mixed
    {
        $blockEnv = new Environment($env);
        // Assign args to params
        foreach ($block->getParams() as $i => $param) {
            $blockEnv->set($param, $args[$i] ?? null);
        }
        $blockEnv->set("self", $target);
        $lastValue = null;
        foreach ($block->getStatements() as $stmt) {
            $lastValue = $this->interpretStatement($stmt, $target, $blockEnv);
        }

        return $lastValue;
    }

    private function interpretStatement(SOLStatement $stmt, SOLObject $target, Environment $env)
    {
        return 1;
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