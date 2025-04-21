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

    /** @var OutputWriter $stderr */
    private OutputWriter $stderr;

    public function __construct(OutputWriter $stderr)
    {
        $this->stderr = $stderr;
    }

    /**
     * Parses the root element of the DOM document.
     * 
     * The root element must be a `<program>` tag with the `language="SOL25"` attribute.
     * All classes, methods, and blocks within the XML are parsed and converted to SOL objects.
     * 
     * @param DOMElement $root The root element of the XML document to parse.
     * @return int Return code indicating the success or failure of the operation.
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
     * Parses a class element from the XML document.
     * 
     * Creates a SOLClass object for the parsed class and its methods.
     *
     * @param DOMElement $classNode The `<class>` element to parse.
     * @return int Return code indicating the success or failure of the operation.
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

            $class->addMethod($selector, new SOLMethod($block));


        }
        $this->classes[$name] = $class;
        return ReturnCode::OK;
    }

    /**
     * Parses a block element from the XML document and creates a SOLBlock object.
     * 
     * @param DOMElement $blockNode The `<block>` element to parse.
     * @return ?SOLBlock The parsed SOLBlock object, or null if the block is invalid.
     */
    private function parseBlock(DOMElement $blockNode): ?SOLBlock
    {
        $params = [];
        $statements = [];
        
        // Parse immediate children only
        foreach ($blockNode->childNodes as $node) {
            if ($node instanceof DOMElement) {
                if ($node->tagName === "parameter") {
                    $paramName = $node->getAttribute("name");
                    if (!empty($paramName)) {
                        $params[] = $paramName;
                    }
                } elseif ($node->tagName === "assign") {
                    $varNode = $node->getElementsByTagName("var")->item(0);
                    $exprNode = $node->getElementsByTagName("expr")->item(0);
                    if ($varNode && $exprNode && $varNode->hasAttribute("name")) {
                        $varName = $varNode->getAttribute("name");
                        $expr = $this->parseExpression($exprNode);
                        if ($expr === null) {
                            $this->stderr->writeString("Invalid expression in assign to $varName\n");
                            return null;
                        }
                        $statements[] = new SOLStatement($varName, $expr);
                    } else {
                        $this->stderr->writeString("Invalid assign in block\n");
                        return null;
                    }
                } else {
                    $this->stderr->writeString("Unexpected node in block: " . $node->tagName . "\n");
                    return null;
                }
            }
        }
        
        return new SOLBlock($params, $statements);
    }

    /**
     * Parses an expression node and converts it to a SOLExpression object.
     * 
     * The method supports literals, variables, method sends, and block expressions.
     *
     * @param DOMElement $exprNode The `<expr>` element to parse.
     * @return ?SOLExpression The corresponding SOLExpression object, or null if parsing failed.
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
                return new SOLLiteral($class, $value);
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
                $targetNode = null;
                foreach ($expressionElement->childNodes as $node) {
                    if ($node instanceof DOMElement && $node->tagName === "expr") {
                        $targetNode = $node;
                        break;
                    }
                }
                if (!$targetNode instanceof DOMElement) {
                    $this->stderr->writeString("Message send missing target expression\n");
                    return null;
                }
                $target = $this->parseExpression($targetNode);
                if ($target === null) {
                    return null;
                }
                $args = [];
                foreach ($expressionElement->childNodes as $node) {
                    if ($node instanceof DOMElement && $node->tagName === "arg") {
                        $argExprNode = null;
                        foreach ($node->childNodes as $child) {
                            if ($child instanceof DOMElement && $child->tagName === "expr") {
                                $argExprNode = $child;
                                break;
                            }
                        }
                        if (!$argExprNode instanceof DOMElement) {
                            $this->stderr->writeString("Argument missing expression\n");
                            return null;
                        }
                        $argExpr = $this->parseExpression($argExprNode);
                        if ($argExpr === null) {
                            return null;
                        }
                        $args[] = $argExpr;
                    }
                }
                return new SOLSend($selector, $target, $args);
            case "block":
                $block = $this->parseBlock($expressionElement);
                if ($block === null) {
                    $this->stderr->writeString("Could not parse block expression\n");
                    return null;
                }
                return new SOLBlockExpression($block);
            default:
                $this->stderr->writeString("Unknown expression type: {$expressionElement->tagName}\n");
                return null;
        }
    }

    /**
     * Getter for the parsed classes.
     * 
     * @return array<string, SOLClass> The array of parsed SOL classes.
     */
    public function getClasses(): array
    {
        return $this->classes;
    }
}