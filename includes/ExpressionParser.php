<?php

// make the MathParser classes in math-parser available here:
require_once __DIR__ . '/autoload.php';

use MathParser\Lexing\TokenDefinition as TokenDefinition;
use MathParser\Lexing\TokenType as TokenType;

// Custom Lexer to support multi-letter variables and disable implicit multiplication
class CustomLexer extends MathParser\Lexing\Lexer
{
    public function __construct()
    {
        $this->add(new TokenDefinition('/\d+[,\.]\d+(e[+-]?\d+)?/', TokenType::RealNumber));
        $this->add(new TokenDefinition('/\d+/', TokenType::PosInt));
        $this->add(new TokenDefinition('/\(/', TokenType::OpenParenthesis));
        $this->add(new TokenDefinition('/\)/', TokenType::CloseParenthesis));
        $this->add(new TokenDefinition('/\+/', TokenType::AdditionOperator));
        $this->add(new TokenDefinition('/\-/', TokenType::SubtractionOperator));
        $this->add(new TokenDefinition('/\*/', TokenType::MultiplicationOperator));
        $this->add(new TokenDefinition('/\//', TokenType::DivisionOperator));
        $this->add(new TokenDefinition('/\^/', TokenType::ExponentiationOperator));
        $this->add(new TokenDefinition('/[a-zA-Z_][a-zA-Z0-9_]*/', TokenType::Identifier));
        $this->add(new TokenDefinition('/\s+/', TokenType::Whitespace));
    }
}

// JavaScript Converter (unchanged)
class JavaScriptConverter implements MathParser\Interpreting\Visitors\Visitor
{
    private $allowedOperations = ['+', '-', '*', '/', '^'];
    private $allowedVariables = [];

    public function __construct(array $allowedVariables = [])
    {
        $this->allowedVariables = $allowedVariables;
    }

    public function visitExpressionNode(MathParser\Parsing\Nodes\ExpressionNode $node)
    {
        $operator = $node->getOperator();
        if (!in_array($operator, $this->allowedOperations)) {
            throw new MathParser\Exceptions\UnknownOperatorException("Unsupported operator: $operator");
        }

        $left = $node->getLeft()->accept($this);
        $right = $node->getRight() ? $node->getRight()->accept($this) : null;

        if ($right === null) {
            return "-$left";
        }

        $jsOperator = $operator === '^' ? '**' : $operator;
        return "($left $jsOperator $right)";
    }

    public function visitNumberNode(MathParser\Parsing\Nodes\NumberNode $node)
    {
        return (string)$node->getValue();
    }

    public function visitVariableNode(MathParser\Parsing\Nodes\VariableNode $node)
    {
        $varName = $node->getName();
        if (!empty($this->allowedVariables) && !in_array($varName, $this->allowedVariables)) {
            throw new Exception("Invalid variable: $varName");
        }
        return 'RT.cookie.getCookie("'.$varName.'")';
    }

    public function visitFunctionNode(MathParser\Parsing\Nodes\FunctionNode $node)
    {
        throw new MathParser\Exceptions\UnknownFunctionException("Functions are not allowed: {$node->getName()}");
    }

    public function visitIntegerNode($node) { return (string)$node->getValue(); }
    public function visitRationalNode($node) { return (string)$node->getValue(); }
    public function visitConstantNode($node) { throw new Exception("Constants are not allowed: {$node->getName()}"); }
}

class ParserWithoutImplicitMultiplication extends MathParser\Parsing\Parser {
    protected static function allowImplicitMultiplication() {
        return false;
    }
}

class RiskModelExpressionParser extends MathParser\AbstractMathParser
{
    public function __construct()
    {
        $this->lexer = new CustomLexer();
	$this->parser = new ParserWithoutImplicitMultiplication();
    }
    /**
     * Parse the given mathematical expression into an abstract syntax tree.
     *
     * @param string $text Input
     * @return Node
     */
    public function parse($text)
    {
        $this->tokens = $this->lexer->tokenize($text);
        $this->tree = $this->parser->parse($this->tokens);

        return $this->tree;
    }
}

function convertToJavaScript(string $expression, array $allowedVariables = []): string
{
    $parser = new RiskModelExpressionParser();
    $ast = $parser->parse($expression);
    $converter = new JavaScriptConverter($allowedVariables);
    return $ast->accept($converter);
}
