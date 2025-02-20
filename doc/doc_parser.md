# Implementation Documentation for Task No. 1 in IPP 2024/2025
**Name and Surname:** Michal Repčík
**Login:** xrepcim00

---
## Overview
This Python script implements a lexer, parser, and semantic analyzer for a simple imperative object-oriented programming language called SOL25. The program reads source code from standard input, tokenizes it, constructs an Abstract Syntax Tree (AST), performs basic syntax and semantic validation, and outputs the XML representation of the AST to the standard output. The implementation is modular, with separate classes for each component.

## Key Components
### Token
The 'Token' class represents token with two attributes: 'token_type' and 'value'. Additionally, this class includes a method called 'check_token', which verifies whether the token matches an expected type and value.
### Lexer 
The 'Lexer' class is responsible for tokenizing the source code. It uses a set of predefined regular expressions to identify and categorize tokens. This process utilizes 're' library to define and match patterns for different token types. The 'token_touple_arr' attribute contains touples of token types and regular expressions. The 'compile_regex' method combines all regular expressions into single regex patern, which is used to identify matched tokens. The 'tokenize' method processes source code using the compiled regex pattern to find matches and populate array of tokens.
### Parser
The 'Parser' class is responsible for processing array of tokens obtained from lexer, construcing AST and checking for syntax errors. This class uses multiple methods for recursive descent parsing with the help of 'consume_token' and 'advance_token' methods responsible for validating 'token_type' and traversing token array.