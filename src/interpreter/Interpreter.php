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
    /**
     * @var array<string, SOLClass> Map of class names to their corresponding SOLClass instances.
     */
    private array $classes = [];

    /**
     * Find a class by name, optionally handling a 'class' indirection.
     *
     * @param string $name Name of the class or the string 'class'
     * @param string|null $value Value used when name is 'class'
     * @return SOLClass|null Returns the found class or null if not found
     */
    public function findClass(string $name, ?string $value = null): ?SOLClass
    {
        if ($name == 'class') {
            return $this->findClass($value);
        }
        elseif (!isset($this->classes[$name])) {
            $this->stderr->writeString("Error: Class '$name' not found\n");
            exit(ReturnCode::PARSE_UNDEF_ERROR);
        }
        return $this->classes[$name];
    }

    /**
     * Checks if a class is a subclass of another class.
     *
     * @param SOLClass $member Class to check
     * @param string $inheritedClass Name of the parent class
     * @return bool True if $member is derived from $inheritedClass
     */
    public function isMemberOf(SOLClass $member, string $inheritedClass): bool
    {
        $currentClass = $member;
        while ($currentClass !== null) {
            if ($currentClass->getName() === $inheritedClass) {
                return true;
            }
            $parentName = $currentClass->getParentName();
            if ($parentName === null) {
                return false;
            }
            $currentClass = $this->findClass($parentName);
        }
        return false;
    }

    /**
     * Determines whether a method exists in a class or its parent hierarchy.
     *
     * @param SOLClass $class The class to search in
     * @param string $selector The method name
     * @return bool True if method is found
     */
    public function hasMethod(SOLClass $class, string $selector): bool
    {
        $method = $class->getMethod($selector);
        if ($method !== null) {
            return true;
        }

        // Check parent class
        $parentName = $class->getParentName();
        if ($parentName !== null) {
            $parentClass = $this->findClass($parentName);
            if ($parentClass !== null) {
                return $this->hasMethod($parentClass, $selector);
            }
        }
        return false;
    }

    /**
     * Finds a method in the given class or its parent classes.
     *
     * @param SOLClass $class Class to search in
     * @param string $selector Method name
     * @return SOLMethod|null Returns the found method or exits with error if not found
     */
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
        exit(ReturnCode::INTERPRET_DNU_ERROR);
    }

    /**
     * Executes the interpreter on the provided input XML.
     *
     * @return int ReturnCode indicating the result of execution
     */
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
        if ($runMethod === null) {
            $this->stderr->writeString("Error: run method not found in Main\n");
            exit(ReturnCode::PARSE_MAIN_ERROR);
        }

        $runBlock = $runMethod->getBlock();
        // Create instance of main class
        $mainObj = new SOLObject($mainClass);

        // Initialize global environment
        $globalEnv = new Environment();
        $globalEnv->set("self", $mainObj);

        // Create block object for run method
        $this->interpretBlock($runBlock, $mainObj, [], $globalEnv);
        
        return ReturnCode::OK;
    }

    /**
     * Executes a block of code.
     *
     * @param SOLBlock $block The block to execute
     * @param SOLObject $target Target object ('self') for this block
     * @param array<mixed> $args Arguments passed to the block
     * @param Environment $env Current environment
     * @return mixed The last evaluated result of the block
     */
    private function interpretBlock(SOLBlock $block, SOLObject $target, array $args, Environment $env): mixed
    {
        $blockEnv = new Environment($env);
        // Assign args to params
        foreach ($block->getParams() as $i => $param) {
            if (!isset($args[$i])) {
                $this->stderr->writeString("Error: Missing argument for parameter $param at index $i\n");
                exit(ReturnCode::INTERPRET_TYPE_ERROR);
            }
            // Extract the internal value of the argument
            $argval = $args[$i]->getInternalValue();
            // Bind the parameter to an SOLObject wrapping the value
            $class = $this->findClass($args[$i]->getClass()->getName());
            $blockEnv->set($param, new SOLObject($class, $argval));
        }
        $blockEnv->set("self", $target);
        $lastValue = null;
        foreach ($block->getStatements() as $stmt) {
            $lastValue = $this->interpretStatement($stmt, $target, $blockEnv);
        }

        return $lastValue;
    }

    /**
     * Interprets a single statement and updates the environment.
     *
     * @param SOLStatement $stmt Statement to interpret
     * @param SOLObject $target Current target object
     * @param Environment $env Environment where the statement runs
     * @return mixed The result of the evaluated expression
     */
    private function interpretStatement(SOLStatement $stmt, SOLObject $target, Environment $env): mixed
    {
        $varName = $stmt->getVarName();
        $expr = $stmt->getExpr();
        $value = $this->evaluateExpression($expr, $target, $env);
        $env->set($varName, $value);
        return $value;
    }

    /**
     * Evaluates an expression and returns its value.
     *
     * @param SOLExpression $expr Expression to evaluate
     * @param SOLObject $target Target object ('self') context
     * @param Environment $env Current environment
     * @return mixed Resulting value of the expression
     */
    private function evaluateExpression(SOLExpression $expr, SOLObject $target, Environment $env): mixed
    {
        if ($expr instanceof SOLLiteral) {
            $className = $expr->getClass();
            $value = $expr->getValue();
            $class = $this->findClass($className, $value);
            return new SOLObject($class, $value);
        }
        elseif ($expr instanceof SOLBlockExpression) {
            $class = $this->findClass('Block');
            // Extract SOLBlock from expression and instantiate the block
            $block = $expr->getBlock();
            return new SOLObject($class, $block);
        }
        elseif ($expr instanceof SOLVariable) {
            $varName = $expr->getName();
            // Get the object that the variable is tracking from environment
            $value = $env->get($varName);
            if ($value === null) {
                $this->stderr->writeString("Variable $varName does not exist in current scope\n");
                exit(ReturnCode::INTERPRET_TYPE_ERROR);
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
            
            // Handle potential self reference
            $selfObj = $this->handleSelf($receiver, $selector, $args, $env);
            if ($selfObj !== null) {
                return $selfObj;
            }

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

    /**
     * Handles self-reference and attribute access.
     *
     * @param SOLObject $receiver The receiving object
     * @param string $msg Message or method name
     * @param array<mixed> $args Arguments to apply
     * @param Environment $env Current environment
     * @return SOLObject|null The result if handled, or null
     */
    private function handleSelf(SOLObject $receiver, string $msg, array $args, Environment $env): ?SOLObject
    {
        $self = $env->get("self");
        if ($self !== $receiver) {
            return null;
        }

        if ($this->hasMethod($self->getClass(), $msg)) {
            $method = $this->findMethod($self->getClass(), $msg);
            if ($method->isNative()) {
                $native = $method->getNative();
                return $native($receiver, $args, $env);
            }
            else {
                $block = $method->getBlock();
                return $this->interpretBlock($block, $receiver, $args, $env);
            }
        }

        if (str_ends_with($msg, ':')) {
            $attrName = rtrim($msg, ':'); // Remove trailing :
            $receiver->setVar($attrName, $args[0]);
            return $receiver;
        }
        $value = $receiver->getVar($msg);
        if ($value !== null) {
            return $value;
        }
        exit(ReturnCode::INTERPRET_DNU_ERROR);
    }

    /**
     * Initializes built in method, classes and pupulates
     * the associative array containing classes.
     *
     * @return void
     */
    private function initializeBuiltIn(): void
    {
        // True
        $trueClass = new SOLClass('True', 'Object');
        // not
        $trueClass->addMethod('not', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                return new SOLObject($this->findClass('False'), null);
            }
        ));
        $trueClass->addMethod('and:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                if (count($args) !== 1 || $args[0]->getClass()->getName() !== 'Block') {
                    $this->stderr->writeString("Error: and: expects 1 Block argument\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                if (!$receiver->getClass()->getName() == 'False') {
                    return new SOLObject($this->findClass('False'), null);
                }
                else {
                    $argResult = $this->interpretBlock($args[0]->getInternalValue(), $receiver, [], $env);
                    if ($argResult->getClass()->getName() === 'False') {
                        return new SOLObject($this->findClass('False'), null);
                    }
                }
                return new SOLObject($this->findClass('True'), null);
            }
        ));
        // or:
        $trueClass->addMethod('or:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                if (count($args) !== 1 || $args[0]->getClass()->getName() !== 'Block') {
                    $this->stderr->writeString("Error: or: expects 1 Block argument\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                return $receiver;
            }
        ));
        // ifTrue:ifFalse:
        $trueClass->addMethod('ifTrue:ifFalse:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): ?SOLObject {
                if (count($args) !== 2 || $args[0]->getClass()->getName() !== 'Block' || $args[1]->getClass()->getName() !== 'Block') {
                    $this->stderr->writeString("Error: ifTrue:ifFalse: expects 2 Block arguments\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                if ($receiver->getClass()->getName() === 'True') {
                    return $this->interpretBlock($args[0]->getInternalValue(), $receiver, [], $env);
                }
                else {
                    return $this->interpretBlock($args[1]->getInternalValue(), $receiver, [], $env);
                }
            }
        ));
        $this->classes['True'] = $trueClass;
        // False
        $falseClass = new SOLClass('False', 'Object');
        // not
        $falseClass->addMethod('not', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                return new SOLObject($this->findClass('True'), null);
            }
        ));
        $falseClass->addMethod('or:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                if (count($args) !== 1 || $args[0]->getClass()->getName() !== 'Block') {
                    $this->stderr->writeString("Error: or: expects 1 Block argument\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                return new SOLObject($this->findClass("False"), null);
            }
        ));
        $falseClass->addMethod('ifTrue:ifFalse:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): ?SOLObject {
                if (count($args) !== 2 || $args[0]->getClass()->getName() !== 'Block' || $args[1]->getClass()->getName() !== 'Block') {
                    $this->stderr->writeString("Error: ifTrue:ifFalse: expects 2 Block arguments\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                if ($receiver->getClass()->getName() === 'False') {
                    return $this->interpretBlock($args[1]->getInternalValue(), $receiver, [], $env);
                }
                else {
                    return $this->interpretBlock($args[0]->getInternalValue(), $receiver, [], $env);
                }
            }
        ));
        $this->classes['False'] = $falseClass;
        
        // Object
        $objectClass = new SOLClass('Object', null);
        $objectClass->addMethod('new', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                return new SOLObject($this->findClass($receiver->getClass()->getName()));
            }
        ));
        $objectClass->addMethod('from:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                return new SOLObject($this->findClass($receiver->getClass()->getName()), $args[0]->getInternalValue());
            }
        ));
        // identicalTo
        $objectClass->addMethod('identicalTo:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $trueClass = $this->findClass('True');
                $falseClass = $this->findClass('False');
                $isIdentical = $receiver->getClass()->getName() === $args[0]->getClass()->getName();
                $isIdentical2 = $receiver->getInternalValue() === $args[0]->getInternalValue();
                return new SOLObject(($isIdentical and $isIdentical2) ? $trueClass : $falseClass, null);
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
        $trueInstance = new SOLObject($trueClass, null); // Instance False

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
                $argClass = $args[0]->getClass();
                if (!$this->isMemberOf($argClass, "Integer")) {
                    $argClassName = $argClass->getName();
                    $this->stderr->writeString("Addition by $argClassName is not allowed\n");
                    exit(ReturnCode::INTERPRET_VALUE_ERROR);
                }
                $result = $receiver->getInternalValue() + $args[0]->getInternalValue();
                return new SOLObject($this->findClass('Integer'), $result);
            }
        ));
        // minus:
        $integerClass->addMethod('minus:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $argClass = $args[0]->getClass();
                if (!$this->isMemberOf($argClass, "Integer")) {
                    exit(ReturnCode::INTERPRET_VALUE_ERROR);
                }
                $result = $receiver->getInternalValue() - $args[0]->getInternalValue();
                return new SOLObject($this->findClass('Integer'), $result);
            }
        ));
        // multiplyBy:
        $integerClass->addMethod('multiplyBy:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $argClass = $args[0]->getClass();
                if (!$this->isMemberOf($argClass, "Integer")) {
                    $argClassName = $argClass->getName();
                    $this->stderr->writeString("Multiplication by $argClassName is not allowed\n");
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
                $argClass = $args[0]->getClass();
                if (!$this->isMemberOf($argClass, "Integer")) {
                    $argClassName = $argClass->getName();
                    $this->stderr->writeString("Division by $argClassName is not allowed\n");
                    exit(ReturnCode::INTERPRET_VALUE_ERROR);
                }
                $result = intdiv((int) $receiver->getInternalValue(), (int) $args[0]->getInternalValue());
                return new SOLObject($this->findClass('Integer'), (string) $result);
            }
        ));
        // asString
        $integerClass->addMethod('asString', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $res = $receiver->getInternalValue();
                return new SOLObject($this->findClass('String'), $receiver->getInternalValue());
            }
        ));
        // asInteger
        $integerClass->addMethod('asInteger', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                return $receiver;
            }
        ));
        $integerClass->addMethod('isNumber', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env) use ($trueInstance): SOLObject {
                return $trueInstance;
            }
        ));
        // timeRepeat
        // notimplementedyet
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
        $nilClass->addMethod('isNil', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env) use ($trueInstance): SOLObject {
                return $trueInstance;
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
                $this->stdout->writeString($receiver->getInternalValue());
                return $receiver;
            }
        ));
        // equalTo:
        $stringClass->addMethod('equalTo:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): SOLObject {
                $receiverValue = $receiver->getInternalValue();
                $argValue = $args[0]->getInternalValue();
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
        $stringClass->addMethod('isString', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env) use ($trueInstance): SOLObject {
                return $trueInstance;
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
                $argClass = $args[0]->getClass();
                if (!$this->isMemberOf($argClass, "String")) {
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
                $isIntStart = $this->isMemberOf($startObj->getClass(), "Integer");
                $isIntEnd = $this->isMemberOf($endObj->getClass(), "Integer");
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
        $blockClass->addMethod('isBlock', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env) use ($trueInstance): SOLObject {
                return $trueInstance;
            }
        ));
        // value (0 arguments)
        $blockClass->addMethod('value', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): mixed {
                $block = $receiver->getInternalValue();
                if (!$block instanceof SOLBlock) {
                    $this->stderr->writeString("Error: Receiver is not a block\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                if (count($block->getParams()) !== 0) {
                    $this->stderr->writeString("Error: Block expects " . count($block->getParams()) . " arguments, but 0 provided\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                return $this->interpretBlock($block, $receiver, [], $env);
            }
        ));
        // value: (1 argument)
        $blockClass->addMethod('value:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): mixed {
                $block = $receiver->getInternalValue();
                if (!$block instanceof SOLBlock) {
                    $this->stderr->writeString("Error: Receiver is not a block\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                if (count($block->getParams()) !== 1) {
                    $this->stderr->writeString("Error: Block expects " . count($block->getParams()) . " arguments, but 1 provided\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                if (count($args) !== 1) {
                    $this->stderr->writeString("Error: value: expects 1 argument, got " . count($args) . "\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                return $this->interpretBlock($block, $receiver, $args, $env);
            }
        ));
        // value:value: (2 arguments)
        $blockClass->addMethod('value:value:', new SOLMethod(
            function (SOLObject $receiver, array $args, Environment $env): mixed {
                $block = $receiver->getInternalValue();
                if (!$block instanceof SOLBlock) {
                    $this->stderr->writeString("Error: Receiver is not a block\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                if (count($block->getParams()) !== 2) {
                    $this->stderr->writeString("Error: Block expects " . count($block->getParams()) . " arguments, but 2 provided\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                if (count($args) !== 2) {
                    $this->stderr->writeString("Error: value:value: expects 2 arguments, got " . count($args) . "\n");
                    exit(ReturnCode::INTERPRET_TYPE_ERROR);
                }
                return $this->interpretBlock($block, $receiver, $args, $env);
            }
        ));
        // whileTrue:
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
     * 
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