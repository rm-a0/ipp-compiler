#!/usr/bin/env python3.11

import sys
import re

# Error returns
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

TOKENS = [
    ("CLASS_T", r'class'),
    ("SELF_T", r'self'),
    ("SUPER_T", r'super'),
    ("NIL_T", r'nil'),
    ("TRUE_T", r'true'),
    ("FALSE_T", r'false'),
]
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