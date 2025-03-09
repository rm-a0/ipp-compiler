#!/usr/bin/env python3.11
import sys
import re
import argparse
import xml.etree.ElementTree as ET
from xml.dom import minidom
from enum import Enum
from abc import ABC, abstractmethod
from typing import Pattern, Tuple, List, Optional

class ErrorType(Enum):
    """
    Predefined error types
    """
    MISSING_PARAM = 10
    ERROR_STDIN = 11
    ERROR_STDOUT = 12
    LEXICAL_ERROR = 21
    SYNTAX_ERROR = 22
    SEMANTIC_ERROR_MISSING_MAIN = 31
    SEMANTIC_ERROR_UNDEFINED_USE = 32
    SEMANTIC_ERROR_MISSMATCH = 33
    SEMANTIC_ERROR_VAR_COLLISION = 34
    SEMANTIC_ERROR_OTHER = 35
    INTERNAL_ERROR = 99

def print_err(msg: str, error_type: ErrorType) -> None:
    """
    Print error message and exit with specified error 
    """
    print(f"Error: {msg}", file=sys.stderr)
    sys.exit(error_type.value)

class TokenType(Enum):
    """
    Token types mapped to strings
    """
    # Reserved keywords
    CLASS_KW = "class_"
    SELF_KW = "self"
    SUPER_KW = "super"
    NIL_KW = "Nil"
    TRUE_KW = "True"
    FALSE_KW = "False"
    # Built-in classes
    OBJECT_BC = "Object"
    NIL_BC = "Nil_"
    TRUE_BC = "True_"
    FALSE_BC = "False_"
    INT_BC = "Integer_"
    STRING_BC = "String_"
    BLOCK_BC = "Block_"
    # Identifiers
    IDENTIFIER = "Identifier"
    SELECTOR = "Selector"
    CLASS_IDENTIFIER = "class"
    # Other tokens
    ASSIGN = "assign"
    DOT = "dot"
    COLON = "colon"
    L_BRACE = "l_brace"
    R_BRACE = "r_brace"
    L_BRACKET = "l_bracket"
    R_BRACKET = "r_bracket"
    L_PARENT = "l_parent"
    R_PARENT = "r_parent"
    PIPE = "pipe"
    STRING = "String"
    INTEGER = "Integer"
    # Whitespace and comments
    WHITESPACE = "WHITESPACE"
    NEWLINE = "NEWLINE"
    COMMENT = "COMMENT"
    # Invalid token
    INVALID = "INVALID"
    EOF = "EOF"

class Token: 
    """
    Represents a token in SOL25 language. Contains type and optionally a value    
    """
    def __init__(self, token_type, value=None):
        self.type = token_type
        self.value = value
    
    def __repr__(self):
        """
        Returns string represnetation of token
        """
        return f"Token(type: {self.type.value}, value: {self.value!r})"

    def check_token(self, expected_type: TokenType, expected_value=None) -> bool:
        """
        Check if token attributes are the same as expected attributes
        """
        if self.type != expected_type:
            return False
        if expected_value is not None and self.value != expected_value:
            return False
        return True

class ASTNode:
    """
    Base class for all AST nodes
    """
    def accept(self, visitor):
        raise NotImplementedError

class ProgramNode(ASTNode):
    """
    Program node contains array of class nodes and fist comment
    """
    def __init__(self, class_nodes: List['ClassNode'], first_comment: str):
        self.first_comment = first_comment
        self.class_nodes = class_nodes

    def accept(self, visitor):
        return visitor.visit_program(self)

class ClassNode(ASTNode):
    """
    Class node contains class identifier, parent class and array of method nodes
    """
    def __init__(self, identifier: str, parent_class: str, methods: List['MethodNode']):
        self.identifier = identifier
        self.parent_class = parent_class
        self.methods = methods

    def accept(self, visitor):
        return visitor.visit_class(self)

class MethodNode(ASTNode):
    """
    Method node contains selector, arity and block node
    """
    def __init__(self, selector: ASTNode, arity: int, block: 'BlockNode'):
        self.selector = selector
        self.arity = arity
        self.block = block

    def accept(self, visitor):
        return visitor.visit_method(self)

class BlockNode(ASTNode):
    """
    Block node contains array of parameters and array of statement nodes
    """
    def __init__(self, parameters: str, statements: List['StatementNode']):
        self.parameters = parameters
        self.param_count = len(parameters)
        self.statements = statements

    def accept(self, visitor):
        return visitor.visit_block(self)

class StatementNode(ASTNode):
    """
    Statement node contains identifier and expression node (send/variable/literal node)
    """
    def __init__(self, identifier: str, expression: ASTNode):
        self.identifier = identifier
        self.expression = expression

    def accept(self, visitor):
        return visitor.visit_statement(self)

class SendNode(ASTNode):
    """
    Send node contains receiver node, select and array of argument 
    nodes (variable/litera/block nodes)
    """
    def __init__(self, receiver: ASTNode, selector: str, args=None):
        self.receiver = receiver
        self.selector = selector
        self.args = args

    def accept(self, visitor):
        return visitor.visit_send(self)

class VariableNode(ASTNode):
    """
    Variable node contains variable identifier
    """
    def __init__(self, name: str):
        self.name = name

    def accept(self, visitor):
        return visitor.visit_variable(self)

class LiteralNode(ASTNode):
    """
    Literal node contains type and value
    """
    def __init__(self, literal_type: str, value: str):
        self.type = literal_type
        self.value = value
    
    def accept(self, visitor):
        return visitor.visit_literal(self)

class ASTVisitor(ABC):
    """
    Abstract interface for visitor methods
    """
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

class XMLVisitor(ASTVisitor):
    """
    Converts AST to desired XML format
    """
    def visit_program(self, node: 'ProgramNode') -> ET.Element:
        """
        Construct element for program, add description, append class elements 
        and return prettified program element
        """
        program = ET.Element('program', language="SOL25")
        if node.first_comment:
            description = node.first_comment
            program.set('description', description)

        for class_node in node.class_nodes:
            program.append(class_node.accept(self))

        return self.prettify(program)

    def visit_class(self, node: 'ClassNode') -> ET.Element:
        """
        Construct and return element for class, append class elements to it
        """
        class_elem = ET.Element('class', name=node.identifier, parent=node.parent_class)

        for method_node in node.methods:
            class_elem.append(method_node.accept(self))

        return class_elem

    def visit_method(self, node: 'MethodNode') -> ET.Element:
        """
        Construct and return method element and append block element to it
        """
        method_elem = ET.Element('method', selector=node.selector)
        method_elem.append(node.block.accept(self))

        return method_elem

    def visit_block(self, node: 'BlockNode') -> ET.Element:
        """
        Construct and return element, add parameter subelement and append assign element
        with variable subbelement to it
        """
        block_elem = ET.Element('block', arity=str(node.param_count))

        for index, parameter in enumerate(node.parameters):
            param_elem = ET.SubElement(block_elem, 'parameter', name=parameter, order=str(index + 1))

        for index, statement in enumerate(node.statements):
            assign_elem = ET.Element('assign', order=str(index + 1))
            var_elem = ET.SubElement(assign_elem, 'var', name=statement.identifier)
            assign_elem.append(statement.accept(self))
            block_elem.append(assign_elem)

        return block_elem

    def visit_statement(self, node: 'StatementNode') -> ET.Element:
        """
        Construct and return element for exrpression and append send element to it 
        """
        expr_elem = ET.Element('expr')
        expr_elem.append(node.expression.accept(self))
        return expr_elem

    def visit_send(self, node) -> ET.Element:
        """
        Construct and return element for send, add receiver subelement, append corresponding
        receiver element and add subelements for arguments with subelements for expression
        """
        send_elem =  ET.Element('send', selector=node.selector)
        receiver_elem = ET.SubElement(send_elem, 'expr')
        receiver_elem.append(node.receiver.accept(self))

        for index, argument in enumerate(node.args):
            arg_elem = ET.SubElement(send_elem, 'arg', order=str(index + 1))
            expr_elem = ET.SubElement(arg_elem, 'expr')
            expr_elem.append(argument.accept(self))

        return send_elem 

    def visit_variable(self, node) -> ET.Element:
        """
        Construct and return variable element
        """
        return ET.Element('var', name=node.name)

    def visit_literal(self, node) -> ET.Element:
        """
        Construct and return literal element
        """
        value = str(node.value)
        # Strip strings
        if value.startswith("'") and value.endswith("'"):
            value = value[1:-1]
        # Dictionary because class is builtin keyword
        return ET.Element('literal', **{'class': node.type, 'value': value})

    def prettify(self, element) -> str:
        """
        Converts an XML element into a prettified string with proper encoding.
        """
        rough_string = ET.tostring(element, encoding="UTF-8", xml_declaration=True)
        rough_string = rough_string.decode("UTF-8")
        parsed = minidom.parseString(rough_string)
        pretty_string = parsed.toprettyxml(indent="  ")

        # Hardcoed encoding because for some reason it was not originally displayed
        if not pretty_string.startswith('<?xml version="1.0" encoding="UTF-8"?>'):
            if pretty_string.startswith('<?xml'):
                end_of_first_line = pretty_string.find('?>') + 2
                pretty_string = pretty_string[end_of_first_line:].lstrip()
            pretty_string = '<?xml version="1.0" encoding="UTF-8"?>\n' + pretty_string

        return pretty_string 

class Lexer:
    """
    Tokenizes SOL25 source code into a list of tokens
    """
    def __init__(self, src_code: str):
        self.src_code = src_code
        self.token_tuple_arr = [
            # Reserved keywords
            (TokenType.SELECTOR, r'[a-z_][a-zA-Z0-9_]*:'),
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
            (TokenType.STRING, r"'([^'\\\n]*(\\['n\\][^'\\\n]*)*)'"),
            (TokenType.INTEGER, r'[+-]?\d+'),
            # Whitespace and comments
            (TokenType.WHITESPACE, r'[ \t]+'),
            (TokenType.NEWLINE, r'\n'),
            (TokenType.COMMENT, r'"(?s:.*?)(?=|\n|$)"'),
            # Invalid token
            (TokenType.INVALID, r'.')
        ]
        # Combine regex patterns
        self.get_token = self.compile_regex()

    # Combine and compile regex patterns
    def compile_regex(self) -> Pattern[str]:
        """
        Join regex patterns and returns compiled pattern
        """
        self.token_regex = '|'.join(f'(?P<{token_type.name}>{pattern})' for token_type, pattern in self.token_tuple_arr)
        return re.compile(self.token_regex).match

    # Populate token array
    def tokenize(self) -> Tuple[List[Token], Optional[str]]:
        """
        Tokenizes the source code and returns a list of tokens along with the first comment (if it exists).
        """
        idx = 0
        comment_flag = False
        first_comment = None
        tokens = []
        while idx < len(self.src_code):
            match = self.get_token(self.src_code, idx)
            if not match:
                print_err("Unexpected regex pattern", ErrorType.LEXICAL_ERROR)

            token_type = match.lastgroup
            token_value = match.group(token_type)
            # Detect invalid tokens, skip/validate comments and whitespace
            if token_type == TokenType.INVALID.value:
                print_err("Invalid token", ErrorType.LEXICAL_ERROR)
            elif token_type == TokenType.COMMENT.value:
                if token_value[-1] != '"':
                    print_err("Unclosed comment detected", ErrorType.LEXICAL_ERROR)
                elif comment_flag == False:
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

class Parser:
    """
    Parses tokenized source code and constructs AST
    """
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
        self.literal_tokens = {
            TokenType.INTEGER,
            TokenType.STRING,
            TokenType.TRUE_KW,
            TokenType.FALSE_KW,
            TokenType.NIL_KW,
            TokenType.CLASS_IDENTIFIER
        }
        self.variable_tokens = {
            TokenType.SELF_KW,
            TokenType.SUPER_KW,
            TokenType.IDENTIFIER
        }

    # Advance current token and increment token_idx if possible
    def advance_token(self) -> None:
        if self.token_idx < self.token_len - 1:
            self.token_idx += 1
            self.current_token = self.tokens[self.token_idx]
        else:
            self.current_token = Token(TokenType.EOF)

    # Check current token if it matches expected type, return current token and advance token
    def consume_token(self, expected_type: TokenType) -> Token:
        if not self.current_token.check_token(expected_type):
            print_err(f"Unexpected token type: {self.current_token}", ErrorType.SYNTAX_ERROR)
        token = self.current_token
        self.advance_token()
        return token

    def peek_token(self, expected_type: TokenType) -> bool:
        if self.token_idx < self.token_len - 1:
            if self.tokens[self.token_idx + 1].check_token(expected_type):
                return True
        return False

    # Parse expression base
    def parse_expression_base(self) -> ASTNode:
        if self.current_token.type in self.literal_tokens:
            token = self.current_token
            self.advance_token()
            return LiteralNode(token.type.value, token.value)
        elif self.current_token.type in self.builtin_classes:
            token = self.current_token
            self.advance_token()
            return LiteralNode('class', token.value)
        elif self.current_token.type in self.variable_tokens:
            token = self.current_token
            self.advance_token()
            return VariableNode(token.value)
        elif self.current_token.check_token(TokenType.L_PARENT):
            self.advance_token()
            expression = self.parse_expression(TokenType.R_PARENT)
            self.consume_token(TokenType.R_PARENT)
            return expression
        elif self.current_token.check_token(TokenType.L_BRACKET):
            block = self.parse_block()
            return block
        else:
            print_err(f"Unexpected token while parsing expression base {self.current_token}", ErrorType.SYNTAX_ERROR)

    # Parse expression
    def parse_expression(self, end_token: TokenType) -> ASTNode:
        """
        Parses an expression until the specified end token is encountered.
        Handles literals, variables, and message sends.
        """
        base = self.parse_expression_base()
        selector_parts = []
        args = []

        while True:
            if self.current_token.check_token(TokenType.IDENTIFIER):
                selector = self.current_token.value
                self.advance_token()
                return SendNode(base, selector, args)
            if self.current_token.check_token(TokenType.SELECTOR):
                selector_parts.append(self.current_token.value)
                self.advance_token()
                arg = self.parse_expression_base()
                args.append(arg)

                if self.current_token.check_token(end_token):
                    return SendNode(base, "".join(selector_parts), args)
            elif self.current_token.check_token(end_token):
                if not selector_parts:
                    return base
                else:
                    return SendNode(base, "".join(selector_parts), args)
            else:
                print_err(f"Unexpected token while parsing expression {self.current_token}", ErrorType.SYNTAX_ERROR)

    # Parse statement
    def parse_statemenet(self) -> ASTNode:
        token_id = self.consume_token(TokenType.IDENTIFIER)
        self.consume_token(TokenType.ASSIGN)
        expression = self.parse_expression(TokenType.DOT)
        self.consume_token(TokenType.DOT)

        return StatementNode(token_id.value, expression)

    # Parse block
    def parse_block(self) -> ASTNode:
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
    def parse_method(self) -> ASTNode:
        selector = ""
        if self.current_token.check_token(TokenType.SELECTOR):
            selector = self.consume_token(TokenType.SELECTOR).value
            param_count = 1
        elif self.current_token.check_token(TokenType.IDENTIFIER):
            selector = self.consume_token(TokenType.IDENTIFIER).value
            param_count = 0

        # Check if there are multiple selectors
        while not self.current_token.check_token(TokenType.L_BRACKET):
            selector += self.consume_token(TokenType.SELECTOR).value
            param_count += 1

        block = self.parse_block()

        return MethodNode(selector, param_count, block)

    # Parse class
    def parse_class(self) -> ASTNode:
        class_id = self.consume_token(TokenType.CLASS_IDENTIFIER)
        self.consume_token(TokenType.COLON)

        # Check if current token type is class identifier or builtin class
        if self.current_token.type not in self.builtin_classes | {TokenType.CLASS_IDENTIFIER}:
            print_err(f"Unexpected token while parsing class {self.current_token}", ErrorType.SYNTAX_ERROR)
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
    def parse_program(self, first_comment: str) -> ASTNode:
        if first_comment == None:
            first_comment = ""
        class_nodes = []
        while not self.current_token.check_token(TokenType.EOF):
            self.consume_token(TokenType.CLASS_KW)
            class_nodes.append(self.parse_class())

        return ProgramNode(class_nodes, first_comment)

class SemanticAnalyzer(ASTVisitor):
    """
    Perform semantic checks on constructed AST
    """
    def __init__(self):
        self.class_symtable = {
            "Object": {
                "parent": None,
                "methods": {
                    "new" : {"arity": 0},
                    "from:" : {"arity": 1},
                    "identicalTo:": {"arity": 1},
                    "equalTo:": {"arity": 1},
                    "asString": {"arity": 0},
                    "isNumber": {"arity": 0},
                    "isString": {"arity": 0},
                    "isBlock": {"arity": 0},
                    "isNil": {"arity": 0},
                }
            },
            "Nil": {
                "parent": "Object",
                "methods": {
                    "asString": {"arity": 0},
                }
            },
            "Integer": {
                "parent": "Object",
                "methods": {
                    "equalTo:": {"arity": 1},
                    "plus:": {"arity": 1},
                    "minus:": {"arity": 1},
                    "multiplyBy:": {"arity": 1},
                    "divBy:": {"arity": 1},
                    "asString": {"arity": 0},
                    "asInteger": {"arity": 0},
                    "timesRepeat:": {"arity": 1},
                }
            },
            "String": {
                "parent": "Object",
                "methods": {
                    "equalTo:": {"arity": 1},
                    "concatenateWith:": {"arity": 1},
                    "startsWith:endsBefore:": {"arity": 2},
                    "asString": {"arity": 0},
                    "asInteger": {"arity": 0},
                    "print": {"arity": 0},
                    "read": {"arity": 0},
                }
            },
            "Block": {
                "parent": "Object",
                "methods": {
                    "value": {"arity": 0},
                    "value:": {"arity": 1},
                    "value:value:": {"arity": 2},
                    "whileTrue:": {"arity": 1},
                }
            },
            "True": {
                "parent": "Object",
                "methods": {
                    "not": {"arity": 0},
                    "and:": {"arity": 1},
                    "or:": {"arity": 1},
                    "ifTrue:ifFalse:": {"arity": 2},
                }
            },
            "False": {
                "parent": "Object",
                "methods": {
                    "not": {"arity": 0},
                    "and:": {"arity": 1},
                    "or:": {"arity": 1},
                    "ifTrue:ifFalse:": {"arity": 2},
                }
            },
        } 
        self.scopes = {
            "global": {
                "variables": {},
                "parameters": {},
                "parent": None,
                "children" : []
            }
        }
        self.scopes["global"]["variables"]["self"] = True
        self.scopes["global"]["variables"]["super"] = True
        self.scopes["global"]["variables"]["nil"] = True
        self.scopes["global"]["variables"]["true"] = True
        self.scopes["global"]["variables"]["false"] = True
        self.current_scope = [self.scopes["global"]]
        self.current_class = None

    def enter_scope(self) -> None:
        new_scope = {
            "variables": {},
            "parameters": {},
            "parent": self.current_scope[-1],
            "children": []
        }
        self.current_scope[-1]["children"].append(new_scope)
        self.current_scope.append(new_scope)

    def exit_scope(self):
        self.current_scope.pop()

    def declare_variable(self, variable: str) -> None:
        if variable == "_":
            return # Skip '_' declaration

        # Check for variable collision with parameter
        if variable in self.current_scope[-1]["parameters"]:
            print_err(f"Variable not within the scope: {variable}", ErrorType.SEMANTIC_ERROR_VAR_COLLISION)

        self.current_scope[-1]["variables"][variable] = True

    def declare_param(self, parameter: str) -> None:
        # Check if parameter names are identical
        if parameter in self.current_scope[-1]["parameters"]:
            print_err(f"Identical names of parameters: {parameter}", ErrorType.SEMANTIC_ERROR_OTHER)

        self.current_scope[-1]["parameters"][parameter] = True

    # Check if variable is in the scope
    def check_variable(self, variable: str) -> bool:
        # Skip '_' check
        if variable == "_":
            return True
        for scope in reversed(self.current_scope):
            if variable in scope["variables"] or variable in scope["parameters"]:
                return True
        return False

    def check_method(self, class_: str, method: str) -> bool:
        while class_:
            methods = self.class_symtable.get(class_, {}).get("methods", {})
            if method in methods:
                return True
            
            class_ = self.class_symtable.get(class_, {}).get("parent")

    def check_circular_dependency(self, class_name: str) -> None:
        """
        Checks for circular inheritance in the class hierarchy
        """
        visited = set()
        temp_class = class_name
        while temp_class:
            if temp_class in visited:
                print_err(f"Circular dependency detected for `{class_name}`", ErrorType.SEMANTIC_ERROR_OTHER)

            visited.add(temp_class)
            temp_class = self.class_symtable.get(temp_class, {}).get("parent")
            

    def analyze(self, ast: ASTNode) -> None:
        self.visit_program(ast)
        self.check_main_class()

    # Check if class identifier 'Main' is in class symbtable
    def check_main_class(self) -> None:
        if "Main" not in self.class_symtable:
            print_err("Missing Main class", ErrorType.SEMANTIC_ERROR_MISSING_MAIN)
        elif "run" not in self.class_symtable["Main"]["methods"]:
            print_err("Missing run in Main class", ErrorType.SEMANTIC_ERROR_MISSING_MAIN)

    def check_class(self, class_id: str) -> None:
        if class_id not in self.class_symtable:
            print_err(f"Undefined class `{class_id}`", ErrorType.SEMANTIC_ERROR_UNDEFINED_USE)

    # Check if number of colons corresponds to number of arguments in method
    def check_arity(self, method_arity: int, param_count: int) -> None:
        if method_arity != param_count:
            print_err(f"Arrity missmatch, expected: {method_arity}, got: {param_count}", ErrorType.SEMANTIC_ERROR_MISSMATCH)

    def visit_program(self, node) -> None:
        # Declare all classes
        for class_node in node.class_nodes:
            class_name = class_node.identifier
            if class_name in self.class_symtable:
                print_err(f"Class `{class_name}` already exists", ErrorType.SEMANTIC_ERROR_OTHER)

            self.class_symtable[class_name] = {
                "parent": None,  
                "methods" : {}
            }

        # Declare all parent classes
        for class_node in node.class_nodes:
            class_name = class_node.identifier
            parent_class = class_node.parent_class
            if parent_class not in self.class_symtable:
                print_err(f"Parent class `{parent_class}` doesnt exist", ErrorType.SEMANTIC_ERROR_UNDEFINED_USE)

            self.class_symtable[class_name]["parent"] = parent_class

        for class_node in node.class_nodes:
            class_node.accept(self)

    def visit_class(self, node) -> None:
        class_name = node.identifier
        self.current_class = class_name

        # Check circular dependency
        self.check_circular_dependency(class_name)
        # First add all methods to symtable (order of declarations doesnt matter)
        for method in node.methods:
            method_id = method.selector
            arity = method.arity
            if method_id in self.class_symtable[class_name]["methods"]:
                print_err(f"Method `{method_id}` already exists", ErrorType.SEMANTIC_ERROR_OTHER)
            self.class_symtable[class_name]["methods"][method_id] = {"arity": arity}

        for method in node.methods:
            method.accept(self)

    def visit_method(self, node) -> None:
        arity = node.arity

        self.check_arity(arity, node.block.param_count)
        
        node.block.accept(self)

    def visit_block(self, node) -> None:
        self.enter_scope()
        for param in node.parameters:
            self.declare_param(param)
        
        for statement in node.statements:
            statement.accept(self)

        self.exit_scope()

    def visit_statement(self, node) -> None:
        self.declare_variable(node.identifier)
        node.expression.accept(self)

    def visit_send(self, node) -> None:
        if hasattr(node.receiver, 'type') and node.receiver.type == "class":
            class_name = node.receiver.value
            if not self.check_method(class_name, node.selector):
                print_err(f"Class `{class_name}` has no method `{node.selector}`", ErrorType.SEMANTIC_ERROR_UNDEFINED_USE)
        node.receiver.accept(self)

        for arg in node.args:
            arg.accept(self)

    def visit_variable(self, node) -> None:
        if self.check_variable(node.name) == False:
            print_err(f" Variable `{node.name}` is not within the scope", ErrorType.SEMANTIC_ERROR_UNDEFINED_USE)

    def visit_literal(self, node) -> None:
        if node.type == "class":
            self.check_class(node.value)

# Main function
def main():
    arg_parser = argparse.ArgumentParser(
        description="Compiler for the imperative object-oriented programming language SOL25.",
        usage=  f"python {sys.argv[0]} < input_file"
    )

    if len(sys.argv) == 2 and (sys.argv[1] in ['-h', '--help']):
        arg_parser.parse_args()
    elif len(sys.argv) != 1:
        print("Incorrect number of parameters", file=sys.stderr)
        sys.exit(ErrorType.MISSING_PARAM.value)

    # Initialize lexer and tokenize input
    lexer = Lexer(sys.stdin.read())
    tokens, first_comment = lexer.tokenize()

    # Initialize parser, check grammar, and construct AST
    parser = Parser(tokens)
    ast_root = parser.parse_program(first_comment)

    # Initialize semantic analyzer and perform semantic analysis
    semantic_analyzer = SemanticAnalyzer()
    semantic_analyzer.analyze(ast_root)

    # Generare XML
    xml_visitor = XMLVisitor()
    print(ast_root.accept(xml_visitor))

if __name__ == "__main__":
    main()
