# Implementation Documentation for Task No. 1 in IPP 2024/2025
**Name and Surname:** Michal Repčík
**Login:** xrepcim00

---
## Overview
This Python script implements a lexer, parser, and semantic analyzer for a simple imperative object-oriented programming language called **SOL25**. The program reads source code from standard input, tokenizes it, constructs an Abstract Syntax Tree (AST), performs basic syntax and semantic validation, and outputs the XML representation of the AST to the standard output. The implementation is modular, with separate classes for each component.

## Key Components
### Token
The `Token` class represents a token with two attributes: `token_type` and `value`. Additionally, this class includes a method called `check_token`, which verifies whether the token matches an expected type and optionally value.

### Lexer
The `Lexer` class is responsible for tokenizing the source code. It uses a set of predefined regular expressions to identify and categorize tokens. This process utilizes the `re` library to define and match patterns for different token types. The `token_tuple_arr` attribute contains tuples of token types and their corresponding regular expressions. The `compile_regex` method combines all regular expressions into a single regex pattern, which is used to identify matched tokens. The `tokenize` method processes the source code using the compiled regex pattern to find matches and populate an array of tokens. This method returns tuple consisitng of `tokens` (used for parsing) and `first_comment` (used for XML generation).

### Parser
The `Parser` class is responsible for processing an array of tokens obtained from the lexer, constructing the AST, and checking for syntax errors. This class uses multiple methods for recursive descent parsing, with the help of the `consume_token`, `advance_token` and `peek_token` methods, which are responsible for validating `token_type` and traversing the token array. After successfull parsing the method 'parse_program' outputs the root of constructed AST.

### Abstract Syntax Tree
The `ASTNode` class represents the hierarchical structure of the parsed source code. Each node in the tree corresponds to a syntactic construct in the SOL25 language, such as expressions, statements, literals or variables. The AST is constructed during parsing and serves as the foundation for semantic analysis and XML generation.

### **AST Visitor**
This `ASTVisitor`class servers as an interface for implementing `XMLVisitor` and `SemanticAnalyzer`.

### XML Visitor
The 'XMLVisitor' class is used for generating XML representation of AST. It utilizes multiple methods for traversing
the AST and converts its structure into formated XML using `ElementTree` from `xml.etree.ElementTree` module and `minidom` from `xml.dom` module.

### Semantic Analyzer

