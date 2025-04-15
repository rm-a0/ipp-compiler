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
use IPP\Core\FileInputReader;

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

    /**
     * Interprets block
     * @param SOLBlock $block
     * @param SOLObject $target
     * @param array<mixed> $args
     * @param Environment $env
     */
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
        // True
        $trueClass = new SOLClass('True', 'Object');
        $this->classes['True'] = $trueClass;
        // False
        $falseClass = new SOLClass('False', 'Object');
        $this->classes['False'] = $falseClass;

        // Object
        $objectClass = new SOLClass('Object', null);
        // identicalTo
        $objectClass->addMethod('identicalTo:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $trueClass = $this->findClass('True');
                $falseClass = $this->findClass('False');
                $isIdentical = $receiver === $args[0];
                return new SOLObject($isIdentical ? $trueClass : $falseClass, null);
            }
        ));
        // equalTo
        $objectClass->addMethod('equalTo:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $trueClass = $this->findClass('True');
                $falseClass = $this->findClass('False');
                $receiverValue = $receiver->getInternalValue();
                $argValue = $args[0]->getInternalValue();

                // If they do not have internal values call identicalTo method
                if ($receiverValue === null && $argValue === null) {
                    $class = $receiver->getClass();
                    $method = $this->findMethod($class, 'identicalTo:');
                    $native = $method->getNative();
                    return $native($receiver, $args, $env);
                }

                // Compare internal values
                $isEqual = $receiverValue == $argValue;
                return new SOLObject($isEqual ? $trueClass : $falseClass, null);
            }
        ));
        // asString
        $objectClass->addMethod('asString', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                return new SOLObject($this->findClass('String'), '');
            }
        ));
        // is{cLass} all return false
        $falseClass = $this->findClass('False');
        $falseInstance = new SOLObject($falseClass, null); // Instance False

        $objectClass->addMethod('isNumber', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env) use ($falseInstance): SOLObject {
                return $falseInstance;
            }
        ));
        $objectClass->addMethod('isString', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env) use ($falseInstance): SOLObject {
                return $falseInstance;
            }
        ));
        $objectClass->addMethod('isBlock', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env) use ($falseInstance): SOLObject {
                return $falseInstance;
            }
        ));
        $objectClass->addMethod('isNil', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env) use ($falseInstance): SOLObject {
                return $falseInstance;
            }
        ));
        $this->classes['Object'] = $objectClass;

        // Interger
        $integerClass = new SOLClass('Integer', 'Object');
        // equalTo
        $integerClass->addMethod('equalTo:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $trueClass = $this->findClass('True');
                $falseClass = $this->findClass('False');
                $isEqual = $receiver->getInternalValue() == $args[0]->getInternalValue();
                return new SOLObject($isEqual ? $trueClass : $falseClass, null);
            }
        ));
        // greaterThen:
        $integerClass->addMethod('greaterThan:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $trueClass = $this->findClass('True');
                $falseClass = $this->findClass('False');
                $isEqual = $receiver->getInternalValue() > $args[0]->getInternalValue();
                return new SOLObject($isEqual ? $trueClass : $falseClass, null);
            }
        ));
        // plus:
        $integerClass->addMethod('plus:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $argClass = $args[0]->getClass()->getName();
                if ($argClass != 'Integer') {
                    $this->stderr->writeString("Addition by $argClass is not allowed\n");
                    exit(ReturnCode::INTERPRET_VALUE_ERROR);
                }
                $result = $receiver->getInternalValue() + $args[0]->getInternalValue();
                return new SOLObject($this->findClass('Integer'), $result);
            }
        ));
        // minus:
        $integerClass->addMethod('minus:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $argClass = $args[0]->getClass()->getName();
                if ($argClass != 'Integer') {
                    $this->stderr->writeString("Substraction by $argClass is not allowed\n");
                    exit(ReturnCode::INTERPRET_VALUE_ERROR);
                }
                $result = $receiver->getInternalValue() - $args[0]->getInternalValue();
                return new SOLObject($this->findClass('Integer'), $result);
            }
        ));
        // multiplyBy:
        $integerClass->addMethod('multiplyBy:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $argClass = $args[0]->getClass()->getName();
                if ($argClass != 'Integer') {
                    $this->stderr->writeString("Multiplication by $argClass is not allowed\n");
                    exit(ReturnCode::INTERPRET_VALUE_ERROR);
                }
                $result = $receiver->getInternalValue() * $args[0]->getInternalValue();
                return new SOLObject($this->findClass('Integer'), $result);
            }
        ));
        // divBy:
        $integerClass->addMethod('divBy:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                if ($args[0]->getInternalValue() == 0) {
                    $this->stderr->writeString("Division by 0 is not allowed\n");
                    exit(ReturnCode::INTERPRET_VALUE_ERROR);
                }
                $argClass = $args[0]->getClass()->getName();
                if ($argClass != 'Integer') {
                    $this->stderr->writeString("Division by $argClass is not allowed\n");
                    exit(ReturnCode::INTERPRET_VALUE_ERROR);
                }
                $result = intdiv((int) $receiver->getInternalValue(), (int) $args[0]->getInternalValue());
                return new SOLObject($this->findClass('Integer'), (string) $result);
            }
        ));
        // asString
        $integerClass->addMethod('asString', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                return new SOLObject($this->findClass('String'), $receiver->getInternalValue());
            }
        ));
        // asInteger
        $integerClass->addMethod('asInteger', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                return $receiver;
            }
        ));
        // timeRepeat
        // TODO
        $this->classes['Integer'] = $integerClass;

        // Nil
        $nilClass = new SOLClass('Nil', 'Object');
        // asString
        $nilClass->addMethod('asString', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $stringClass = $this->findClass('String');
                return new SOLObject($stringClass, 'nil');
            }
        ));
        $this->classes['Nil'] = $nilClass;

        // String
        $stringClass = new SOLClass('String', 'Object');
        // read
        $stringClass->addMethod('read', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $input = fgets(STDIN);
                if ($input === false) {
                    $input = '';
                } else {
                    $input = rtrim($input, "\n\r");
                }
                return new SOLObject($this->findClass('String'), $input);
            }
        ));
        // print
        $stringClass->addMethod('print', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $value = $receiver->getInternalValue();
                if (!is_string($value)) {
                    $this->stderr->writeString("Error: Invalid string for print\n");
                    exit(ReturnCode::INTERPRET_VALUE_ERROR);
                }
                $this->stdout->writeString($value);
                return $receiver;
            }
        ));
        // equalTo:
        $stringClass->addMethod('equalTo:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $receiverValue = $receiver->getInternalValue();
                $argValue = $args[0]->getInternalValue();
                $argClass = $args[0]->getClass()->getName();
                if ($argClass !== 'String') {
                    $this->stderr->writeString("Error: Invalid string for equalTo:\n");
                    exit(ReturnCode::INTERPRET_VALUE_ERROR);
                }
                $trueClass = $this->findClass('True');
                $falseClass = $this->findClass('False');
                return new SOLObject($receiverValue === $argValue ? $trueClass : $falseClass, null);
            }
        ));
        // asString
        $stringClass->addMethod('asString', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                return $receiver;
            }
        ));
        $this->classes['String'] = $stringClass;
        // asInteger
        $stringClass->addMethod('asInteger', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $receiverValue = $receiver->getInternalValue();
                $intValue = filter_var($receiverValue, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
                if ($intValue !== null) {
                    $integerClass = $this->findClass('Integer');
                    return new SOLObject($integerClass, (string)$intValue);
                }
                $nilClass = $this->findClass('Nil');
                return new SOLObject($nilClass, null);
            }
        ));
        // concatenateWith:
        $stringClass->addMethod('concatenateWith:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $receiverValue = $receiver->getInternalValue();
                $argClass = $args[0]->getClass()->getName();
                if ($argClass !== 'String') {
                    $nil = $this->findClass('Nil');
                    return new SOLObject($nil, null);
                }
                return new SOLObject($this->findClass('String'), $receiverValue . $args[0]->getInternalValue());
                
            }
        ));
        // startsWith:endsBefore:
        $stringClass->addMethod('startsWith:endsBefore:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env) use ($stringClass): SOLObject {
                $receiverValue = $receiver->getInternalValue();
                $startObj = $args[0];
                $endObj = $args[1];

                // Check if arguments are Integer objects
                $isIntStart = $startObj->getClass()->getName() === 'Integer';
                $isIntEnd = $endObj->getClass()->getName() === 'Integer';
                if (!$isIntStart || !$isIntEnd) {
                    $nilClass = $this->findClass('Nil');
                    return new SOLObject($nilClass, null);
                }

                // Get integer values (stored as strings)
                $start = $startObj->getInternalValue();
                $end = $endObj->getInternalValue();

                // Validate positive, non-zero integers
                if (!preg_match('/^[1-9]\d*$/', $start) || !preg_match('/^[1-9]\d*$/', $end)) {
                    $nilClass = $this->findClass('Nil');
                    return new SOLObject($nilClass, null);
                }

                // Convert to integers for calculation
                $start = (int)$start;
                $end = (int)$end;

                // Check if difference <= 0
                if ($end - $start <= 0) {
                    return new SOLObject($stringClass, '');
                }

                // Extract substring (1-based to 0-based)
                $length = $end - $start;
                $substring = substr($receiverValue, $start - 1, $length);

                return new SOLObject($stringClass, $substring);
            }
        ));

        // Block
        $blockClass = new SOLClass('Block', 'Object');
        $blockClass->addMethod('whileTrue:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $receiverBlock = $receiver->getInternalValue();
                $argBlock = $args[0];
                if (!$receiverBlock instanceof SOLBlock || $argBlock->getClass()->getName() !== 'Block') {
                    $this->stderr->writeString("Error: whileTrue: requires blocks\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                $trueClass = $this->findClass('True');
                while (true) {
                    $condition = $this->evaluateExpression(
                        new SOLSend('value', new SOLBlockExpression($receiver->getInternalValue()), []),
                        $receiver,
                        $env
                    );
                    if ($condition->getClass() !== $trueClass) {
                        break;
                    }
                    $this->evaluateExpression(
                        new SOLSend('value', new SOLBlockExpression($argBlock->getInternalValue()), []),
                        $argBlock,
                        $env
                    );
                }
                $nilClass = $this->findClass('Nil');
                return new SOLObject($nilClass, null);
            }
        ));
        $this->classes['Block'] = $blockClass;
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