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
     * @return ?SOLBlock Block object
     */
    private function parseBlock(DOMElement $blockNode): ?SOLBlock
    {
        $params = [];
        $statements = [];
        
        // Parse parameters
        foreach($blockNode->getElementsByTagName("parameter") as $paramNode) {
            $paramName = $paramNode->getAttribute("name");
            if (!empty($paramName)) {
                $params[] = $paramName;
            }
        }
        
        // Parse assignments
        foreach($blockNode->getElementsByTagName("assign") as $assignNode) {
            $varNode = $assignNode->getElementsByTagName("var")->item(0);
            $exprNode = $assignNode->getElementsByTagName("expr")->item(0);

            if ($varNode && $exprNode && $varNode->hasAttribute("name")) {
                $varName = $varNode->getAttribute("name");
                $expr = $this->parseExpression($exprNode);
                if ($expr === null) {
                    $this->stderr->writeString("Invalid expression in assign to $varName\n");
                    return null;
                }
                $statements[] = new SOLStatement($varName, $expr);
            }
            else {
                $this->stderr->writeString("Invalid assign in block\n");
                return null;
            }
        }

        return new SOLBlock($params, $statements);
    }

    /**
     * Parses expr element of the DOM document and creates expression object
     * @return ?SOLExpression Expression object
     */
    private function parseExpression(DOMElement $exprNode): ?SOLExpression
    {
        $expressionElement = null;
        foreach ($exprNode->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if ($expressionElement !== null) {
                    $this->stderr->writeString("Expression contains multiple elements\n");
                    return null;
                }
                $expressionElement = $child;
            }
        }

        if ($expressionElement === null) {
            $this->stderr->writeString("Expression missing valid child element\n");
            return null;
        }

        switch ($expressionElement->tagName) {
            case "literal":
                $class = $expressionElement->getAttribute("class");
                $value = $expressionElement->getAttribute("value");
                if (empty($class)) {
                    $this->stderr->writeString("Literal missing class attribute\n");
                    return null;
                }
                return new SOLLiteral($class, $value ?? "");

            case "var":
                $name = $expressionElement->getAttribute("name");
                if (empty($name)) {
                    $this->stderr->writeString("Variable missing name attribute\n");
                    return null;
                }
                return new SOLVariable($name);

            case "send":
                $selector = $expressionElement->getAttribute("selector");
                if (empty($selector)) {
                    $this->stderr->writeString("Message send missing selector\n");
                    return null;
                }

                $targetNode = $expressionElement->getElementsByTagName("expr")->item(0);
                if (!$targetNode instanceof DOMElement) {
                    $this->stderr->writeString("Message send missing target expression\n");
                    return null;
                }
                $target = $this->parseExpression($targetNode);
                if ($target === null) {
                    return null;
                }

                $args = [];
                foreach ($expressionElement->getElementsByTagName("arg") as $argNode) {
                    $argExprNode = $argNode->getElementsByTagName("expr")->item(0);
                    if ($argExprNode instanceof DOMElement) {
                        $argExpr = $this->parseExpression($argExprNode);
                        if ($argExpr === null) {
                            return null;
                        }
                        $args[] = $argExpr;
                    } else {
                        $this->stderr->writeString("Argument missing expression\n");
                        return null;
                    }
                }

                return new SOLSend($selector, $target, $args);
            case "block":
                $block = $this->parseBlock($expressionElement);
                if ($block === null) {
                    $this->stderr->writeString("Could not parse block expression\n");
                    return null;
                }
                return $block;
            default:
                $this->stderr->writeString("Unknown expression type: {$expressionElement->tagName}\n");
                return null;
        }
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