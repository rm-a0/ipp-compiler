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
TOKEN_TOUPLE_ARR = [
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
TOKEN_REGEX = '|'.join(f'(?P<{name}>{pattern})' for name, pattern in TOKEN_TOUPLE_ARR)
get_token = re.compile(TOKEN_REGEX).match

# Reserved keywords to double check identifiers
RES_KEYWORDS = {
    "class", "self", "super", "nil", "true", "false",
    "Object", "Nil", "True", "False", "Integer", "String", "Block"
}

# Token class
class Token: 
    def __init__(self, token_type, value = None):
        self.type = token_type
        self.value = value
    
    def __repr__(self):
        return f"Token(type: {self.type!r}, value: {self.value!r})"

# Lexer class
class Lexer:
    def __init__(self, src_code):
        self.src_code = src_code
        self.tokens = []

    # Populate token array in Lexer
    def tokenize(self):
        idx = 0
        while idx < len(self.src_code):
            match = get_token(self.src_code, idx)
            if not match:
                sys.exit(LEXICAL_ERROR)

            token_type = match.lastgroup
            # Skip whitespace
            if token_type in {"WHITESPACE", "NEWLINE"}:
                pass
            # Create token
            else:
                if token_type in {"IDENTIFIER", "CLASS_IDENTIFIER", "OPERATOR", "STRING", "INTEGER"}:
                    token = Token(token_type, match.group(token_type))
                else:
                    token = Token(token_type)
                self.tokens.append(token)

            idx = match.end()

# Parser class
class Parser:
    def __init__(self, tokens):
        self.tokens = tokens
        self.state = "START"

    def parse(self):
        for token in self.tokens:
            print(token)

def main():
    src_code = sys.stdin.read()
    lexer = Lexer(src_code)
    lexer.tokenize()

    parser = Parser(lexer.tokens)
    parser.parse()

if __name__ == "__main__":
    main()