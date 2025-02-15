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

# Regex for tokens
TOKEN_REG = [
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
    ("R_BRACE", r'\}')
    ("L_BRACKET", r'\['),
    ("R_BRACKET", r'\]'),
    ("PIPE", r'\|'),
    ("OPERATOR", r'[+\-*/]'),
    ("STRING", r"'([^'\\]*(\\['n\\][^'\\]*)*)'"),
    ("INTEGER", r'[+-]?\d+'),
    ("MISMATCH", r'.'),
]

# Reserved keywords to double check Identifiers
RESERVED_KEYWORDS = {
    "class", "self", "super", "nil", "true", "false",
    "Object", "Nil", "True", "False", "Integer", "String", "Block"
}


class Lexer:
    def __init__(self):
        self.tokens = 0

    def tokenize(self):
        return

class Parser:
    def __init__(self, tokens):
        self.tokens = tokens

def main():
    lexer = Lexer()

if __name__ == "__main__":
    main()