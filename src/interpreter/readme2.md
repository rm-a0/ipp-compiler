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

### SOLMethod.php
- Encapsulates a method's body represeted by a `SOLBlock` node.
- Used for distinction between a block object and a class method.

### SOL Classes (AST nodes)
- Represents a component of the **SOL25** language, used for encapsulation and easier interpretation down the line.
    - **SOLClass:** Represents a class definition, containing blocks.
    - **SOLBlock:** Represents a block of code, containing parameters and statements.
    - **SOLStatement:** Represents a single statement, containing variable and expression. 
    - **SOLExpression:** Interface providing abstraction for all expression types.
        - **SOLLiteral** Represents a literal value (e.g. String, Integer, ...).
        - **SOLVariable** Represents a variable reference by name.
        - **SOLSend** Represents a method call (message send).
        - **SOLBlockExperssion** Wrapper for `SOLBlock`, contains block exclusively assigned to variable.



## Class Diagram
### Interpreter Class Diagram
Below is a detailed class diagram focusing only on the part of the interpreter implemented by me — it does not include other classes or interfaces from the `ipp-core` framework.

![class-diagram-detailed](class-diagram-detailed.png)

### IPP-Core Class Diagram
Below is a class diagram containing all classes and interfaces from the `ipp-core` framework. Relationship descriptions and cardinalities have been omitted for simplicity and to avoid including potentially inaccurate information. Interpreter part and classes associated with it were grouped into one entity to make the diagram more readable and less confusing.

![class-diagram-general](class-diagram-general.png)

## Known Issues and Limitations
A few known limitations and deviations from the expected SOL25 behavior are present in this implementation:

- Blocks using `self` might behave unexpectedly. When a block references `self`, it may not always keep the correct object context, especially when stored or passed around.

- Integers and strings are both stored as strings. All literal values are stored as strings internally, which might slow things down and doesn’t strictly follow type separation.

- Built-in classes may not fully match the spec. Some built-in class methods and their initialization don’t exactly follow SOL25 semantics, though they should behave similarly in practice.

- `super` doesn’t work as intended.

- Some methods differ in implementation. A few methods are implemented differently than described in the project spec, but their functionality should still match the expected behavior.