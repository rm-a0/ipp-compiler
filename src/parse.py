#!/usr/bin/env python3.11
import sys
import re

# Error types
MISSING_PARAM = 10
ERROR_STDIN = 11
ERROR_STDOUT = 12
LEXICAL_ERROR = 21
SYNYAX_ERROR = 22
SEMANTIC_ERROR_MISSING_MAIN = 31
SEMANTIC_ERROR_UNDEFINED_USE = 32
SEMANTIC_ERROR_MISSMATCH = 33
SEMANTIC_ERROR_VAR_COLLISION = 34
INTERNAL_ERROR = 99

# Token class
class Token: 
    def __init__(self, token_type, value = None):
        self.type = token_type
        self.value = value
    
    def __repr__(self):
        return f"Token(type: {self.type!r}, value: {self.value!r})"

    # Check if token attributes are the same as expected attributes
    def check_token(self, expected_type, expected_value = None):
        if self.type != expected_type:
            return False
        if expected_value is not None and self.value != expected_value:
            return False
        return True

# AST class
class ASTNode:
    pass

# Lexer class
class Lexer:
    def __init__(self, src_code):
        self.src_code = src_code
        self.tokens = []
        self.token_tuple_arr = [
            # Reserved keywords
            ("CLASS_KW", r'class'),
            ("SELF_KW", r'self'),
            ("SUPER_KW", r'super'),
            ("NIL_KW", r'nil'),
            ("TRUE_KW", r'true'),
            ("FALSE_KW", r'false'),
            # Builtin classes
            ("OBJECT_BC", r'Object'),
            ("NIL_BC", r'Nil'),
            ("TRUE_BC", r'True'),
            ("FALSE_BC", r'False'),
            ("INT_BC", r'Integer'),
            ("STRING_BC", r'String'),
            ("BLOCK_BC", r'Block'),
            # Identifiers
            ("IDENTIFIER", r'[a-z_][a-zA-Z0-9_]*'),
            ("CLASS_IDENTIFIER", r'[A-Z][a-zA-Z0-9]*'),
            # Other tokens
            ("ASSIGN", r':='),
            ("DOT", r'\.'),
            ("COLON", r':'),
            ("L_BRACE", r'\{'),
            ("R_BRACE", r'\}'),
            ("L_BRACKET", r'\['),
            ("R_BRACKET", r'\]'),
            ("PIPE", r'\|'),
            ("OPERATOR", r'[+\-*/]'),
            ("STRING", r"'([^'\\]*(\\['n\\][^'\\]*)*)'"),
            ("INTEGER", r'[+-]?\d+'),
            # Whitespace and comments
            ("WHITESPACE", r'[ \t]+'),
            ("NEWLINE", r'\n'),
            ("COMMENT", r'".*?"'),
            # Invalid token
            ("INVALID", r'.')
        ]
        # Combine regex patterns
        self.get_token = self.compile_regex()

    # Combine and compile regex patterns
    def compile_regex(self):
        self.token_regex = '|'.join(f'(?P<{name}>{pattern})' for name, pattern in self.token_tuple_arr)
        return re.compile(self.token_regex).match

    # Populate token array
    def tokenize(self):
        idx = 0
        while idx < len(self.src_code):
            match = self.get_token(self.src_code, idx)
            if not match:
                sys.exit(LEXICAL_ERROR)

            token_type = match.lastgroup
            # Skip whitespace and comments
            if token_type in {"WHITESPACE", "NEWLINE", "COMMENT"}:
                pass
            # Create token
            else:
                # For identifiers, operators, strings, and integers, include the value
                if token_type in {"IDENTIFIER", "CLASS_IDENTIFIER", "OPERATOR", "STRING", "INTEGER"}:
                    token = Token(token_type, match.group(token_type))
                # Tokens with self-describing types do not need a value
                else:
                    token = Token(token_type)
                self.tokens.append(token)

            idx = match.end()

# Parser class
class Parser:
    def __init__(self, tokens):
        self.tokens = tokens
        self.token_len = len(tokens)
        self.token_idx = 0
        self.current_token = tokens[0] if tokens else None

    # Advance current token and increement token_idx if possible
    def advance_token(self):
        if self.token_idx < self.token_len:
            self.token_idx += 1
            self.current_token = self.tokens[self.token_idx]
        else:
            self.current_token = None

    # Check current token if it matches expected type, return current token and advance token
    def consume_token(self, expected_type):
        if not self.current_token.check_token(expected_type) or self.current_token is None:
            sys.exit(SYNYAX_ERROR)
        token = self.current_token
        self.advance_token()
        return token

    # Parse class
    def parse_class(self):
        return False

    # Parse program
    def parse_program(self):
        while self.current_token is not None:
            self.consume_token("CLASS_KW")
            class_node = self.parse_class
        return 


def main():
    lexer = Lexer(sys.stdin.read())
    lexer.tokenize()

    parser = Parser(lexer.tokens)
    parser.parse_program()

    #semantic_analyzer = SemanticAnalyzer(ast)

if __name__ == "__main__":
    main()