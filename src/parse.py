#!/usr/bin/env python3
import sys
import re
import argparse
from enum import Enum

# Error types
class ErrorType(Enum):
    MISSING_PARAM = 10
    ERROR_STDIN = 11
    ERROR_STDOUT = 12
    LEXICAL_ERROR = 21
    SYNTAX_ERROR = 22
    SEMANTIC_ERROR_MISSING_MAIN = 31
    SEMANTIC_ERROR_UNDEFINED_USE = 32
    SEMANTIC_ERROR_MISSMATCH = 33
    SEMANTIC_ERROR_VAR_COLLISION = 34
    INTERNAL_ERROR = 99

# Enum for token types
class TokenType(Enum):
    # Reserved keywords
    CLASS_KW = "CLASS_KW"
    SELF_KW = "SELF_KW"
    SUPER_KW = "SUPER_KW"
    NIL_KW = "NIL_KW"
    TRUE_KW = "TRUE_KW"
    FALSE_KW = "FALSE_KW"
    # Built-in classes
    OBJECT_BC = "OBJECT_BC"
    NIL_BC = "NIL_BC"
    TRUE_BC = "TRUE_BC"
    FALSE_BC = "FALSE_BC"
    INT_BC = "INT_BC"
    STRING_BC = "STRING_BC"
    BLOCK_BC = "BLOCK_BC"
    # Identifiers
    IDENTIFIER = "IDENTIFIER"
    CLASS_IDENTIFIER = "CLASS_IDENTIFIER"
    # Other tokens
    ASSIGN = "ASSIGN"
    DOT = "DOT"
    COLON = "COLON"
    L_BRACE = "L_BRACE"
    R_BRACE = "R_BRACE"
    L_BRACKET = "L_BRACKET"
    R_BRACKET = "R_BRACKET"
    PIPE = "PIPE"
    OPERATOR = "OPERATOR"
    STRING = "STRING"
    INTEGER = "INTEGER"
    # Whitespace and comments
    WHITESPACE = "WHITESPACE"
    NEWLINE = "NEWLINE"
    COMMENT = "COMMENT"
    # Invalid token
    INVALID = "INVALID"
    EOF = "EOF"

# Token class
class Token: 
    def __init__(self, token_type, value=None):
        self.type = token_type
        self.value = value
    
    def __repr__(self):
        return f"Token(type: {self.type.value}, value: {self.value!r})"

    # Check if token attributes are the same as expected attributes
    def check_token(self, expected_type, expected_value=None):
        if self.type != expected_type:
            return False
        if expected_value is not None and self.value != expected_value:
            return False
        return True

# ASTNode classes
class ASTNode:
    def accept(self, visitor):
        raise NotImplementedError

class ProgramNode(ASTNode):
    def __init__(self, class_nodes):
        self.class_nodes = class_nodes

    def accept(self, visitor):
        return visitor.visit_program(self)

class ClassNode(ASTNode):
    def __init__(self, identifier, class_type, methods):
        self.identifier = identifier
        self.type = class_type
        self.methods = methods

    def accept(self, visitor):
        return visitor.visit_class(self)

class MethodNode(ASTNode):
    def __init__(self, identifiers, block):
        self.identifiers = identifiers
        self.block = block

    def accept(self, visitor):
        return visitor.visit_method(self)

class BlockNode(ASTNode):
    def __init__(self, parameters, statements):
        self.parameters = parameters
        self.statements = statements

    def accept(self, visitor):
        return visitor.visit_block(self)

# AST Visitor class
class ASTVisitor:
    def visit_program(self, node):
        for node in node.class_nodes:
            node.accept(self)

    def visit_class(self, node):
        pass

    def visit_method(self, node):
        pass

    def visit_block(self, node):
        pass

# Lexer class
class Lexer:
    def __init__(self, src_code):
        self.src_code = src_code
        self.token_tuple_arr = [
            # Reserved keywords
            (TokenType.CLASS_KW, r'class'),
            (TokenType.SELF_KW, r'self'),
            (TokenType.SUPER_KW, r'super'),
            (TokenType.NIL_KW, r'nil'),
            (TokenType.TRUE_KW, r'true'),
            (TokenType.FALSE_KW, r'false'),
            # Built-in classes
            (TokenType.OBJECT_BC, r'Object'),
            (TokenType.NIL_BC, r'Nil'),
            (TokenType.TRUE_BC, r'True'),
            (TokenType.FALSE_BC, r'False'),
            (TokenType.INT_BC, r'Integer'),
            (TokenType.STRING_BC, r'String'),
            (TokenType.BLOCK_BC, r'Block'),
            # Identifiers
            (TokenType.IDENTIFIER, r'[a-z_][a-zA-Z0-9_]*'),
            (TokenType.CLASS_IDENTIFIER, r'[A-Z][a-zA-Z0-9]*'),
            # Other tokens
            (TokenType.ASSIGN, r':='),
            (TokenType.DOT, r'\.'),
            (TokenType.COLON, r':'),
            (TokenType.L_BRACE, r'\{'),
            (TokenType.R_BRACE, r'\}'),
            (TokenType.L_BRACKET, r'\['),
            (TokenType.R_BRACKET, r'\]'),
            (TokenType.PIPE, r'\|'),
            (TokenType.OPERATOR, r'[+\-*/]'),
            (TokenType.STRING, r"'([^'\\]*(\\['n\\][^'\\]*)*)'"),
            (TokenType.INTEGER, r'[+-]?\d+'),
            # Whitespace and comments
            (TokenType.WHITESPACE, r'[ \t]+'),
            (TokenType.NEWLINE, r'\n'),
            (TokenType.COMMENT, r'".*?"'),
            # Invalid token
            (TokenType.INVALID, r'.')
        ]
        # Combine regex patterns
        self.get_token = self.compile_regex()

    # Combine and compile regex patterns
    def compile_regex(self):
        self.token_regex = '|'.join(f'(?P<{token_type.name}>{pattern})' for token_type, pattern in self.token_tuple_arr)
        return re.compile(self.token_regex).match

    # Populate token array
    def tokenize(self):
        idx = 0
        tokens = []
        while idx < len(self.src_code):
            match = self.get_token(self.src_code, idx)
            if not match:
                sys.exit(ErrorType.LEXICAL_ERROR.value)

            token_type = match.lastgroup
            token_value = match.group(token_type)

            # Skip whitespace and comments
            if token_type in {TokenType.WHITESPACE.value, TokenType.NEWLINE.value, TokenType.COMMENT.value}:
                pass
            # Create token
            else:
                # Convert token_type string to TokenType enum
                token_type_enum = TokenType[token_type]
                # For identifiers, operators, strings, and integers, include the value
                if token_type_enum in {TokenType.IDENTIFIER, TokenType.CLASS_IDENTIFIER, TokenType.OPERATOR, TokenType.STRING, TokenType.INTEGER}:
                    token = Token(token_type_enum, token_value)
                # Tokens with self-describing types do not need a value
                else:
                    token = Token(token_type_enum)
                tokens.append(token)

            idx = match.end()

        return tokens

# Parser class
class Parser:
    def __init__(self, tokens):
        self.tokens = tokens
        self.token_len = len(tokens)
        self.token_idx = 0
        self.current_token = tokens[0] if tokens else None
        self.builtin_classes = {
            TokenType.OBJECT_BC,
            TokenType.NIL_BC,
            TokenType.TRUE_BC,
            TokenType.FALSE_BC,
            TokenType.INT_BC,
            TokenType.STRING_BC,
            TokenType.BLOCK_BC,
        }
        self.builtin_keywords = {
            TokenType.NIL_KW,
            TokenType.SELF_KW,
            TokenType.TRUE_KW,
            TokenType.CLASS_KW,
            TokenType.FALSE_KW,
            TokenType.SUPER_KW,
        }

    # Advance current token and increment token_idx if possible
    def advance_token(self):
        if self.token_idx < self.token_len - 1:
            self.token_idx += 1
            self.current_token = self.tokens[self.token_idx]
        else:
            self.current_token = Token(TokenType.INVALID)

    # Check current token if it matches expected type, return current token and advance token
    def consume_token(self, expected_type: TokenType):
        if self.current_token is None or not self.current_token.check_token(expected_type):
            sys.exit(ErrorType.SYNTAX_ERROR.value)
        token = self.current_token
        self.advance_token()
        return token

    # Parse block
    def parse_block(self):
        return "TODO"

    # Parse method
    def parse_method(self):
        identifiers = []
        identifiers.append(self.consume_token(TokenType.IDENTIFIER))

        # Check if there are multiple identifiers
        if self.current_token.check_token(TokenType.COLON):
            self.advance_token()
            while not self.current_token.check_token(TokenType.L_BRACKET):
                identifiers.append(self.consume_token(TokenType.IDENTIFIER))
                self.consume_token(TokenType.COLON)

        block = self.parse_block()

        return MethodNode(identifiers, block)

    # Parse class
    def parse_class(self):
        class_id = self.consume_token(TokenType.CLASS_IDENTIFIER)
        self.consume_token(TokenType.COLON)

        # Check if current token type is in builtin classes
        if self.current_token.type not in self.builtin_classes:
            sys.exit(ErrorType.SYNTAX_ERROR.value)
        class_type = self.current_token
        self.advance_token()
        self.consume_token(TokenType.L_BRACE)

        # Populate method array
        methods = []
        while not self.current_token.check_token(TokenType.R_BRACE):
            methods.append(self.parse_method())

        self.consume_token(TokenType.R_BRACE)

        return ClassNode(class_id, class_type, methods)

    # Parse program and return root of AST (Program node)
    def parse_program(self):
        class_nodes = []
        while self.current_token is not None:
            self.consume_token(TokenType.CLASS_KW)
            class_nodes.append(self.parse_class())

        return ProgramNode(class_nodes)

# Main function
def main():
    arg_parser = argparse.ArgumentParser(
        description="Compiler for the imperative object-oriented programming language SOL25.",
        usage=  f"python {sys.argv[0]} < input_file"
    )

    if len(sys.argv) > 1:
        arg_parser.parse_args()

    # Initialize lexer and tokenize input
    lexer = Lexer(sys.stdin.read())
    tokens = lexer.tokenize()

    # Initialize parser, check grammar, and construct AST
    parser = Parser(tokens)
    ast_root = parser.parse_program()

    # Initialize semantic analyzer and perform semantic analysis
    # semantic_analyzer = SemanticAnalyzer(ast_root)

if __name__ == "__main__":
    main()