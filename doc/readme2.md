# Documentation of Project Implementation for IPP 2024/2025
**Name and Surname:** Michal Repčík
**Login:** xrepcim00

---
## Overview
The SOL25 Interpreter is a PHP-based implementation of the **SOL25** programming language, designed for interpreting object-oriented programs built on top of `ipp-core` framework. Input is an Abstract Syntax Tree (AST) defined in XML format.
SOL25 supports classes, methods, blocks, message sends, and built-in types like `Integer`, `String`, `True`, `False`, `Nil`, and `Block`. The interpreter processes XML input, parses it into an Abstract Syntax Tree, and executes the program starting from the `Main` class’s `run` method.

## Key Components
### XMLParser.php
- Parses XML input into `SOLClass` objects, validating the `<program>` root and class/method structure.
- Converts XML nodes into `SOLBlock`, `SOLStatement`, and `SOLExpression` objects (e.g., SOLLiteral, SOLSend).

### Environmnent.php
- Manages variable scopes using a parent-child hierarchy.
- Stores variables as `array<string, SOLObject>` accessed via `set(string, SOLObject)` and `get(string): ?SOLObject`.
- Used for method and block environments, with `self` binding for the current object.

### SOLObject.php
- Represents an object in the **SOL25** programming language, used by the interpreter to simulate and manipulate objects during program execution.
- Acts as the runtime representation of objects and.
- Holds all the dynamic information an object would have during interpretation: 
    - `array<string, SOLObject>` for storing instance variables (SOLObjects)
    - `mixed` internal value (this can be SOLObject, SOLBlockExpression, string ...)

### SOL Classes (AST nodes)