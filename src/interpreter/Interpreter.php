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

    public function findClass(string $name): ?SOLClass
    {
        if (!isset($this->classes[$name])) {
            $this->stderr->writeString("Error: Class '$name' not found\n");
            exit(ReturnCode::PARSE_UNDEF_ERROR);
        }
        return $this->classes[$name];
    }

    public function findMethod(SOLClass $class, string $selector): ?SOLMethod
    {
        $method = $class->getMethod($selector);
        if ($method !== null) {
            return $method;
        }

        // Check parent class
        $parentName = $class->getParentName();
        if ($parentName !== null) {
            $parentClass = $this->findClass($parentName);
            if ($parentClass !== null) {
                return $this->findMethod($parentClass, $selector);
            }
        }

        $this->stderr->writeString("Error: Method '$selector' not found\n");
        exit(ReturnCode::PARSE_UNDEF_ERROR);
    }

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
            exit($parseResult);
        }
        
        $this->initializeBuiltIn();

        // Extract parsed classes from XMLParser
        $this->classes = array_merge($this->classes, $xmlParser->getClasses());
        if (!isset($this->classes["Main"])) {
            $this->stderr->writeString("Error: Main class not found\n");
            exit(ReturnCode::PARSE_MAIN_ERROR);
        }

        $mainClass = $this->classes["Main"];
        $runMethod = $mainClass->getMethod("run");
        $runBlock = $runMethod->getBlock();
        if ($runBlock === null) {
            $this->stderr->writeString("Error: run method not found in Main\n");
            exit(ReturnCode::PARSE_MAIN_ERROR);
        }

        // Create instance of main class
        $mainObj = new SOLObject($mainClass);

        // Initialize global environment
        $globalEnv = new Environment();
        $globalEnv->set("self", $mainObj);

        // Create block object for run method
        $lastObj = $this->interpretBlock($runBlock, $mainObj, [], $globalEnv);
        if ($lastObj === null) {
            $this->stdout->writeString("Run object results in null\n");
        } elseif ($lastObj instanceof SOLObject) {
            $this->stdout->writeString("Last value is instance of SOLObject\n");
            $className = $lastObj->getClass()->getName();
            $value = $lastObj->getInternalValue();
            if (is_scalar($value)) {
                $this->stdout->writeString("[$className: $value]\n");
            } else {
                $this->stdout->writeString("[$className]\n");
            }
        }
        
        return ReturnCode::OK;
    }

    private function interpretBlock(SOLBlock $block, SOLObject $target, array $args, Environment $env): mixed
    {
        $blockEnv = new Environment($env);
        // Assign args to params
        foreach ($block->getParams() as $i => $param) {
            // Should args be objects?
            $blockEnv->set($param, $args[$i] ?? null);
        }
        $blockEnv->set("self", $target);
        $lastValue = null;
        foreach ($block->getStatements() as $stmt) {
            $lastValue = $this->interpretStatement($stmt, $target, $blockEnv);
        }

        return $lastValue;
    }

    private function interpretStatement(SOLStatement $stmt, SOLObject $target, Environment $env): mixed
    {
        $varName = $stmt->getVarName();
        $expr = $stmt->getExpr();
        $value = $this->evaluateExpression($expr, $target, $env);
        // Set variable in current scope and assign var/object to it
        $env->set($varName, $value);
        return $value;
    }

    private function evaluateExpression(SOLExpression $expr, SOLObject $target, Environment $env): mixed
    {
        if ($expr instanceof SOLLiteral) {
            $className = $expr->getClass();
            $value = $expr->getValue();
            $class = $this->findClass($className);
            if ($class === null) {
                // TODO
                return null;
            }
            return new SOLObject($class, $value);
        }
        elseif ($expr instanceof SOLBlockExpression) {
            $class = $this->findClass('Block');
            if ($class === null) {
                // TODO
                return null;
            }
            // Extract SOLBlock from expression and instantiate the block
            $block = $expr->getBlock();
            return new SOLObject($class, $block);
        }
        elseif ($expr instanceof SOLVariable) {
            $varName = $expr->getName();
            // Get the object that the variable is tracking from environment
            $value = $env->get($varName);
            if ($value === null) {
                // TODO
                return null;
            }
            return $value;
        }
        elseif ($expr instanceof SOLSend) {
            // Transform receiver into SOLObject
            $receiver = $this->evaluateExpression($expr->getReceiver(), $target, $env);

            // Get selector and evaluate arguments
            $selector = $expr->getSelector();
            $args = array_map(fn($arg) => $this->evaluateExpression($arg, $target, $env), $expr->getArgs());

            // Find method in receiver class
            $class = $receiver->getClass();
            $method = $this->findMethod($class, $selector);

            // evaluate method if its native or user defined
            if ($method->isNative()) {
                $native = $method->getNative();
                return $native($receiver, $args, $env);
            }
            else {
                $block = $method->getBlock();
                return $this->interpretBlock($block, $receiver, $args, $env);
            }
        }
        return null;
    }

    private function initializeBuiltIn(): void
    {
        $objectClass = new SOLClass('Object', null);
        $this->classes['Object'] = $objectClass;

        $integerClass = new SOLClass('Integer', 'Object');
        $this->classes['Integer'] = $integerClass;

        $blockClass = new SOLClass('Block', 'Object');
        $this->classes['Block'] = $blockClass;

        $nilClass = new SOLClass('Nil', 'Object');
        $this->classes['Nil'] = $nilClass;

        $stringClass = new SOLClass('String', 'Object');
        $this->classes['String'] = $stringClass;

        $trueClass = new SOLClass('True', 'Object');
        $this->classes['True'] = $trueClass;

        $falseClass = new SOLClass('False', 'Object');
        $this->classes['False'] = $falseClass;
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