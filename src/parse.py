#!/usr/bin/env python3.11
import sys
import re
import argparse
import xml.etree.ElementTree as ET
from xml.dom import minidom
from enum import Enum
from abc import ABC, abstractmethod

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
    L_PARENT = "L_PARENT"
    R_PARENT = "R_PARENT"
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
    def __init__(self, class_nodes, first_comment):
        self.first_comment = first_comment
        self.class_nodes = class_nodes

    def accept(self, visitor):
        return visitor.visit_program(self)

class ClassNode(ASTNode):
    def __init__(self, identifier, parent_class, methods):
        self.identifier = identifier
        self.parent_class = parent_class
        self.methods = methods

    def accept(self, visitor):
        return visitor.visit_class(self)

class MethodNode(ASTNode):
    def __init__(self, selector, param_count, block):
        self.selector = selector
        self.param_count = param_count
        self.block = block

    def accept(self, visitor):
        return visitor.visit_method(self)

class BlockNode(ASTNode):
    def __init__(self, parameters, statements):
        self.parameters = parameters
        self.statements = statements

    def accept(self, visitor):
        return visitor.visit_block(self)

class StatementNode(ASTNode):
    def __init__(self, identifier, expression):
        self.identifier = identifier
        self.expression = expression

    def accept(self, visitor):
        return visitor.visit_statement(self)

class SendNode(ASTNode):
    def __init__(self, receiver, selector, args=None):
        self.receiver = receiver
        self.selector = selector
        self.args = args

    def accept(self, visitor):
        return visitor.visit_send(self)

class VariableNode(ASTNode):
    def __init__(sefl, name):
        self.name = name

    def accept(self, visitor):
        return visitor.visit_variable(self)

class LiteralNode(ASTNode):
    def __init__(self, literal_type, value):
        self.type = literal_type
        self.value = value
    
    def accept(self, visitor):
        return visitor.visit_literal(self)

class SelectorNode(ASTNode):
    def __init__(self, identifier, expression_sel, args=None):
        self.identifier = identifier
        self.args = args
        self.selector = expression_sel

    def accept(self, visitor):
        return visitor.visit_selector(self)

# ASTVisitor interface
class ASTVisitor(ABC):
    @abstractmethod
    def visit_program(self, node):
        pass
    @abstractmethod
    def visit_class(self, node):
        pass
    @abstractmethod
    def visit_method(self, node):
        pass
    @abstractmethod
    def visit_block(self, node):
        pass
    @abstractmethod
    def visit_statement(self, node):
        pass
    @abstractmethod
    def visit_send(self, node):
        pass
    @abstractmethod
    def visit_variable(self, node):
        pass
    @abstractmethod
    def visit_literal(self, node):
        pass

# XMLVisitor class
class XMLVisitor(ASTVisitor):
    def visit_program(self, node):
        program = ET.Element('program', language="SOL25")
        if node.first_comment:
            program.set('description', node.first_comment)

        for class_node in node.class_nodes:
            program.append(class_node.accept(self))

        return self.prettify(program)

    def visit_class(self, node):
        class_elem = ET.Element('class', name=node.identifier, parent=node.parent_class)

        for method_node in node.methods:
            class_elem.append(method_node.accept(self))

        return class_elem

    def visit_method(self, node):
        method_elem = ET.Element('method', selector=node.selector)
        method_elem.append(node.block.accept(self))

        return method_elem

    def visit_block(self, node):
        block_elem = ET.Element('block', arity=str(len(node.parameters)))

        for index, parameter in enumerate(node.parameters):
            param_elem = ET.SubElement(block_elem, 'parameter', name=parameter, order=str(index + 1))

        for index, statement in enumerate(node.statements):
            assign_elem = ET.Element('assign', order=str(index + 1))
            var_elem = ET.SubElement(assign_elem, 'var', name=statement.identifier)
            assign_elem.append(statement.accept(self))
            block_elem.append(assign_elem)

        return block_elem

    def visit_statement(self, node):
        expr_elem = ET.Element('expr')
        expr_elem.append(node.expression.accept(self))
        return expr_elem

    def visit_send(self, node):
        send_elem =  ET.Element('send', selector=node.selector)
        receiver_elem = ET.SubElement(send_elem, 'expr')
        receiver_elem.append(node.receiver.accept(self))

        for index, argument in enumerate(node.args):
            arg_elem = ET.SubElement(send_elem, 'arg', order=str(index + 1))
            expr_elem = ET.SubElement(arg_elem, 'expr')
            expr_elem.append(argument.accept(self))

        return send_elem 

    def visit_variable(self, node):
        pass
    def visit_literal(self, node):
        return ET.Element('literal', _class=node.type.value, value=node.value)

    def prettify(self, element):
        rough_string = ET.tostring(element, encoding="UTF-8", xml_declaration=True)
        rough_string = rough_string.decode("UTF-8")
        parsed = minidom.parseString(rough_string)
        pretty_string = parsed.toprettyxml(indent="  ")

        # Hardcoed encoding because it didnt work
        if not pretty_string.startswith('<?xml version="1.0" encoding="UTF-8"?>'):
            if pretty_string.startswith('<?xml'):
                end_of_first_line = pretty_string.find('?>') + 2
                pretty_string = pretty_string[end_of_first_line:].lstrip()
            pretty_string = '<?xml version="1.0" encoding="UTF-8"?>\n' + pretty_string

        return pretty_string 

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
            (TokenType.L_PARENT, r'\('),
            (TokenType.R_PARENT, r'\)'),
            (TokenType.PIPE, r'\|'),
            (TokenType.OPERATOR, r'[+\-*/]'),
            (TokenType.STRING, r"'([^'\\]*(\\['n\\][^'\\]*)*)'"),
            (TokenType.INTEGER, r'[+-]?\d+'),
            # Whitespace and comments
            (TokenType.WHITESPACE, r'[ \t]+'),
            (TokenType.NEWLINE, r'\n'),
            (TokenType.COMMENT, r'"(?s:.*?)"'),
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
        comment_flag = False
        first_comment = None
        tokens = []
        while idx < len(self.src_code):
            match = self.get_token(self.src_code, idx)
            if not match:
                sys.exit(ErrorType.LEXICAL_ERROR.value)

            token_type = match.lastgroup
            token_value = match.group(token_type)

            # Skip whitespace and comments
            if token_type == TokenType.COMMENT.value and comment_flag == False:
                first_comment = token_value.strip('"')
                comment_flag = True
            elif token_type in {TokenType.WHITESPACE.value, TokenType.NEWLINE.value, TokenType.COMMENT.value}:
                pass
            # Create token
            else:
                # Convert token_type string to TokenType enum
                token_type_enum = TokenType[token_type]
                token = Token(token_type_enum, token_value)
                tokens.append(token)

            idx = match.end()

        return tokens, first_comment

# Parser class
class Parser:
    def __init__(self, tokens):
        self.tokens = tokens
        self.token_len = len(tokens)
        self.token_idx = 0
        self.current_token = tokens[0] if tokens else Token(TokenType.EOF)
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
            self.current_token = Token(TokenType.EOF)

    # Check current token if it matches expected type, return current token and advance token
    def consume_token(self, expected_type: TokenType):
        if not self.current_token.check_token(expected_type):
            sys.exit(ErrorType.SYNTAX_ERROR.value)
        token = self.current_token
        self.advance_token()
        return token

    def peek_token(self, expected_type: TokenType):
        if self.token_idx < self.token_len - 1:
            if self.tokens[self.token_idx + 1].check_token(expected_type):
                return True
        return False

    # Parse expression base
    def parse_expression_base(self):
        if self.current_token.type in {TokenType.IDENTIFIER, TokenType.STRING, TokenType.INTEGER, TokenType.CLASS_IDENTIFIER} | self.builtin_classes | self.builtin_keywords:
            token = self.current_token
            self.advance_token()
            return LiteralNode(token.type, token.value)
        elif self.current_token.check_token(TokenType.L_PARENT):
            self.advance_token()
            expression = self.parse_expression(TokenType.R_PARENT)
            self.consume_token(TokenType.R_PARENT)
            return expression
        elif self.current_token.check_token(TokenType.L_BRACKET):
            block = self.parse_block()
            return block
        else:
            sys.exit(ErrorType.SYNTAX_ERROR.value)

    # Parse expression
    def parse_expression(self, end_token):
        base = self.parse_expression_base()
        selector_parts = []
        args = []

        while True:
            selector_parts.append(self.consume_token(TokenType.IDENTIFIER).value)
            if self.current_token.check_token(TokenType.COLON):
                selector_parts.append(":")
                self.advance_token()
                arg = self.parse_expression_base()
                args.append(arg)

                if self.current_token.check_token(end_token):
                    return SendNode(base, "".join(selector_parts), args)
            elif self.current_token.check_token(end_token):
                return SendNode(base, "".join(selector_parts), args)
            else:
                sys.exit(ErrorType.SYNTAX_ERROR.value)

    # Parse statement
    def parse_statemenet(self):
        token_id = self.consume_token(TokenType.IDENTIFIER)
        self.consume_token(TokenType.ASSIGN)
        expression = self.parse_expression(TokenType.DOT)
        self.consume_token(TokenType.DOT)

        return StatementNode(token_id.value, expression)

    # Parse block
    def parse_block(self):
        self.consume_token(TokenType.L_BRACKET)
        parameters = []
        while not self.current_token.check_token(TokenType.PIPE):
            self.consume_token(TokenType.COLON)
            parameters.append(self.consume_token(TokenType.IDENTIFIER).value)
        self.consume_token(TokenType.PIPE)

        statements = []
        while not self.current_token.check_token(TokenType.R_BRACKET):
            statements.append(self.parse_statemenet())
        self.consume_token(TokenType.R_BRACKET)

        return BlockNode(parameters, statements)

    # Parse method
    def parse_method(self):
        selector = self.consume_token(TokenType.IDENTIFIER).value
        param_count = 0

        # Check if there are multiple identifiers
        if self.current_token.check_token(TokenType.COLON):
            selector += ":"
            param_count += 1
            self.advance_token()
            while not self.current_token.check_token(TokenType.L_BRACKET):
                selector += self.consume_token(TokenType.IDENTIFIER).value
                selector += self.consume_token(TokenType.COLON).value
                param_count += 1

        block = self.parse_block()

        return MethodNode(selector, param_count, block)

    # Parse class
    def parse_class(self):
        class_id = self.consume_token(TokenType.CLASS_IDENTIFIER)
        self.consume_token(TokenType.COLON)

        # Check if current token type is in builtin classes
        if self.current_token.type not in self.builtin_classes:
            sys.exit(ErrorType.SYNTAX_ERROR.value)
        parent_class = self.current_token
        self.advance_token()
        self.consume_token(TokenType.L_BRACE)

        # Populate method array
        methods = []
        while not self.current_token.check_token(TokenType.R_BRACE):
            methods.append(self.parse_method())

        self.consume_token(TokenType.R_BRACE)

        return ClassNode(class_id.value, parent_class.value, methods)

    # Parse program and return root of AST (Program node)
    def parse_program(self, first_comment):
        if first_comment == None:
            first_comment = ""
        class_nodes = []
        while not self.current_token.check_token(TokenType.EOF):
            self.consume_token(TokenType.CLASS_KW)
            class_nodes.append(self.parse_class())

        return ProgramNode(class_nodes, first_comment)

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
    tokens, first_comment = lexer.tokenize()

    # Initialize parser, check grammar, and construct AST
    parser = Parser(tokens)
    ast_root = parser.parse_program(first_comment)

    # Initialize semantic analyzer and perform semantic analysis
    # semantic_analyzer = SemanticAnalyzer(ast_root)

    # Generare XML
    xml_visitor = XMLVisitor()
    print(ast_root.accept(xml_visitor))

if __name__ == "__main__":
    main()
