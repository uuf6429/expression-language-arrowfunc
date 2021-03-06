<?php

namespace uuf6429\ExpressionLanguage;

use uuf6429\ExpressionLanguage\Node\ArrowFuncNode;
use Symfony\Component\ExpressionLanguage\Node\ArgumentsNode;
use Symfony\Component\ExpressionLanguage\Node\ArrayNode;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\ConditionalNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\FunctionNode;
use Symfony\Component\ExpressionLanguage\Node\GetAttrNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\Node\UnaryNode;
use Symfony\Component\ExpressionLanguage\Token;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\ExpressionLanguage\Parser as SymfonyParser;
use Symfony\Component\ExpressionLanguage\TokenStream as SymfonyTokenStream;

class Parser extends SymfonyParser
{
    public const OPERATOR_LEFT = 1;
    public const OPERATOR_RIGHT = 2;

    private const TOKEN_REPLACEMENT_TYPE = 'replacement';

    /** @var TokenStream */
    private $stream;

    /** @var array */
    private $unaryOperators;

    /** @var array */
    private $binaryOperators;

    /** @var array */
    private $functions;

    /** @var string[] */
    private $names;

    /** @var Node[] */
    private $replacementNodes;

    public function __construct(array $functions)
    {
        parent::__construct($functions);

        $this->functions = $functions;

        $this->unaryOperators = array(
            'not' => array('precedence' => 50),
            '!' => array('precedence' => 50),
            '-' => array('precedence' => 500),
            '+' => array('precedence' => 500),
        );
        $this->binaryOperators = array(
            '->' => array('precedence' => 5, 'associativity' => self::OPERATOR_LEFT),
            'or' => array('precedence' => 10, 'associativity' => self::OPERATOR_LEFT),
            '||' => array('precedence' => 10, 'associativity' => self::OPERATOR_LEFT),
            'and' => array('precedence' => 15, 'associativity' => self::OPERATOR_LEFT),
            '&&' => array('precedence' => 15, 'associativity' => self::OPERATOR_LEFT),
            '|' => array('precedence' => 16, 'associativity' => self::OPERATOR_LEFT),
            '^' => array('precedence' => 17, 'associativity' => self::OPERATOR_LEFT),
            '&' => array('precedence' => 18, 'associativity' => self::OPERATOR_LEFT),
            '==' => array('precedence' => 20, 'associativity' => self::OPERATOR_LEFT),
            '===' => array('precedence' => 20, 'associativity' => self::OPERATOR_LEFT),
            '!=' => array('precedence' => 20, 'associativity' => self::OPERATOR_LEFT),
            '!==' => array('precedence' => 20, 'associativity' => self::OPERATOR_LEFT),
            '<' => array('precedence' => 20, 'associativity' => self::OPERATOR_LEFT),
            '>' => array('precedence' => 20, 'associativity' => self::OPERATOR_LEFT),
            '>=' => array('precedence' => 20, 'associativity' => self::OPERATOR_LEFT),
            '<=' => array('precedence' => 20, 'associativity' => self::OPERATOR_LEFT),
            'not in' => array('precedence' => 20, 'associativity' => self::OPERATOR_LEFT),
            'in' => array('precedence' => 20, 'associativity' => self::OPERATOR_LEFT),
            'matches' => array('precedence' => 20, 'associativity' => self::OPERATOR_LEFT),
            '..' => array('precedence' => 25, 'associativity' => self::OPERATOR_LEFT),
            '+' => array('precedence' => 30, 'associativity' => self::OPERATOR_LEFT),
            '-' => array('precedence' => 30, 'associativity' => self::OPERATOR_LEFT),
            '~' => array('precedence' => 40, 'associativity' => self::OPERATOR_LEFT),
            '*' => array('precedence' => 60, 'associativity' => self::OPERATOR_LEFT),
            '/' => array('precedence' => 60, 'associativity' => self::OPERATOR_LEFT),
            '%' => array('precedence' => 60, 'associativity' => self::OPERATOR_LEFT),
            '**' => array('precedence' => 200, 'associativity' => self::OPERATOR_RIGHT),
        );

        $this->replacementNodes = array();
    }

    /**
     * Converts a token stream to a node tree.
     *
     * The valid names is an array where the values
     * are the names that the user can use in an expression.
     *
     * If the variable name in the compiled PHP code must be
     * different, define it as the key.
     *
     * For instance, ['this' => 'container'] means that the
     * variable 'container' can be used in the expression
     * but the compiled code will use 'this'.
     *
     * @param TokenStream $stream A token stream instance
     * @param array       $names  An array of valid names
     *
     * @return Node A node tree
     *
     * @throws SyntaxError
     */
    public function parse(SymfonyTokenStream $stream, $names = array())
    {
        $this->names = $names;

        $stream = $this->preParseArrowFuncs($stream);

        $this->stream = $stream;
        $node = $this->parseExpression();

        if (!$stream->isEOF()) {
            throw new SyntaxError(
                sprintf(
                    'Unexpected token "%s" of value "%s"',
                    $stream->current->type,
                    $stream->current->value
                ),
                $stream->current->cursor
            );
        }

        return $node;
    }

    /**
     * Replaces all anonymous functions with placeholder tokens.
     *
     * @param TokenStream $stream
     *
     * @return TokenStream
     */
    protected function preParseArrowFuncs(TokenStream $stream)
    {
        while (!$stream->isEOF()) {
            if ($stream->current->test(Token::OPERATOR_TYPE, '->')) {
                $operatorPos = $stream->position();
                $operatorCursor = $stream->current->cursor;
                $replacementNodeIndex = count($this->replacementNodes);
                $this->replacementNodes[$replacementNodeIndex] = null;

                // parse parameters
                $parameterNames = array();
                $parameterNodes = array();
                $expectParam = true;
                $stream->prev();
                $stream->expectPrev(Token::PUNCTUATION_TYPE, ')', 'Parameter list must end with parenthesis');
                while (!$stream->current->test(Token::PUNCTUATION_TYPE, '(')) {
                    if ($expectParam) {
                        $stream->current->test(Token::NAME_TYPE);
                        array_unshift($parameterNames, $stream->current->value);
                        array_unshift($parameterNodes, new NameNode($stream->current->value));
                        $stream->prev();
                    } else {
                        $stream->expectPrev(Token::PUNCTUATION_TYPE, ',', 'Parameters must be separated by a comma');
                    }
                    $expectParam = !$expectParam;
                }
                $startPos = $stream->position();

                // parse body
                $stream->seek($operatorPos, SEEK_SET);
                $stream->next();
                $stream->expect(Token::PUNCTUATION_TYPE, '{', 'Anonymous function body must start with a curly bracket');
                $bodyTokens = array();
                $openingBracketCount = 1;
                while ($openingBracketCount != 0) {
                    if ($stream->current->test(Token::PUNCTUATION_TYPE, '{')) {
                        ++$openingBracketCount;
                    }

                    if ($stream->current->test(Token::PUNCTUATION_TYPE, '}')) {
                        --$openingBracketCount;
                    }

                    if (!$openingBracketCount) {
                        $currentNames = $this->names;
                        $currentStream = $this->stream;

                        $bodyTokens[] = new Token(Token::EOF_TYPE, null, 0);
                        $bodyNode = $this->parse(
                            new TokenStream($bodyTokens),
                            array_merge($currentNames, $parameterNames)
                        );

                        $this->names = $currentNames;
                        $this->stream = $currentStream;
                        break;
                    }

                    $bodyTokens[] = $stream->current;
                    $stream->next();
                }
                $stream->expect(Token::PUNCTUATION_TYPE, '}', 'Anonymous function body must end with a curly bracket');
                $endPos = $stream->position();

                // update token stream
                $this->replacementNodes[$replacementNodeIndex] = new ArrowFuncNode($parameterNodes, $bodyNode);
                $replacement = new Token(static::TOKEN_REPLACEMENT_TYPE, $replacementNodeIndex, $operatorCursor);
                $stream = $stream->splice($startPos, $endPos - $startPos, array($replacement));

                // keep parsing anonymous functions
                $stream->seek($startPos, SEEK_SET);
            }

            $stream->next();
        }
        $stream->rewind();

        return $stream;
    }

    public function parseExpression($precedence = 0)
    {
        $expr = $this->getPrimary();
        $token = $this->stream->current;
        while ($token->test(Token::OPERATOR_TYPE) && isset($this->binaryOperators[$token->value]) && $this->binaryOperators[$token->value]['precedence'] >= $precedence) {
            $op = $this->binaryOperators[$token->value];
            $this->stream->next();

            $expr1 = $this->parseExpression(self::OPERATOR_LEFT === $op['associativity'] ? $op['precedence'] + 1 : $op['precedence']);
            $expr = new BinaryNode($token->value, $expr, $expr1);

            $token = $this->stream->current;
        }

        if (0 === $precedence) {
            return $this->parseConditionalExpression($expr);
        }

        return $expr;
    }

    protected function getPrimary()
    {
        $token = $this->stream->current;

        if ($token->test(Token::OPERATOR_TYPE) && isset($this->unaryOperators[$token->value])) {
            $operator = $this->unaryOperators[$token->value];
            $this->stream->next();
            $expr = $this->parseExpression($operator['precedence']);

            return $this->parsePostfixExpression(new UnaryNode($token->value, $expr));
        }

        if ($token->test(Token::PUNCTUATION_TYPE, '(')) {
            $this->stream->next();
            $expr = $this->parseExpression();
            $this->stream->expect(Token::PUNCTUATION_TYPE, ')', 'An opened parenthesis is not properly closed');

            return $this->parsePostfixExpression($expr);
        }

        return $this->parsePrimaryExpression();
    }

    protected function parseConditionalExpression($expr): Node
    {
        while ($this->stream->current->test(Token::PUNCTUATION_TYPE, '?')) {
            $this->stream->next();
            if (!$this->stream->current->test(Token::PUNCTUATION_TYPE, ':')) {
                $expr2 = $this->parseExpression();
                if ($this->stream->current->test(Token::PUNCTUATION_TYPE, ':')) {
                    $this->stream->next();
                    $expr3 = $this->parseExpression();
                } else {
                    $expr3 = new ConstantNode(null);
                }
            } else {
                $this->stream->next();
                $expr2 = $expr;
                $expr3 = $this->parseExpression();
            }

            $expr = new ConditionalNode($expr, $expr2, $expr3);
        }

        return $expr;
    }

    public function parsePrimaryExpression()
    {
        $token = $this->stream->current;
        switch ($token->type) {
            case Token::NAME_TYPE:
                $this->stream->next();
                switch ($token->value) {
                    case 'true':
                    case 'TRUE':
                        return new ConstantNode(true);

                    case 'false':
                    case 'FALSE':
                        return new ConstantNode(false);

                    case 'null':
                    case 'NULL':
                        return new ConstantNode(null);

                    default:
                        if ('(' === $this->stream->current->value) {
                            if (false === isset($this->functions[$token->value])) {
                                throw new SyntaxError(sprintf('The function "%s" does not exist', $token->value), $token->cursor);
                            }

                            $node = new FunctionNode($token->value, $this->parseArguments());
                        } else {
                            if (!in_array($token->value, $this->names, true)) {
                                throw new SyntaxError(sprintf('Variable "%s" is not valid', $token->value), $token->cursor);
                            }

                            // is the name used in the compiled code different
                            // from the name used in the expression?
                            if (is_int($name = array_search($token->value, $this->names))) {
                                $name = $token->value;
                            }

                            $node = new NameNode($name);
                        }
                }
                break;

            case Token::NUMBER_TYPE:
            case Token::STRING_TYPE:
                $this->stream->next();

                return new ConstantNode($token->value);

            case static::TOKEN_REPLACEMENT_TYPE:
                $this->stream->next();

                return $this->replacementNodes[$token->value];

            default:
                if ($token->test(Token::PUNCTUATION_TYPE, '[')) {
                    $node = $this->parseArrayExpression();
                } elseif ($token->test(Token::PUNCTUATION_TYPE, '{')) {
                    $node = $this->parseHashExpression();
                } else {
                    throw new SyntaxError(sprintf('Unexpected token "%s" of value "%s"', $token->type, $token->value), $token->cursor);
                }
        }

        return $this->parsePostfixExpression($node);
    }

    public function parseArrayExpression(): ArrayNode
    {
        $this->stream->expect(Token::PUNCTUATION_TYPE, '[', 'An array element was expected');

        $node = new ArrayNode();
        $first = true;
        while (!$this->stream->current->test(Token::PUNCTUATION_TYPE, ']')) {
            if (!$first) {
                $this->stream->expect(Token::PUNCTUATION_TYPE, ',', 'An array element must be followed by a comma');

                // trailing ,?
                if ($this->stream->current->test(Token::PUNCTUATION_TYPE, ']')) {
                    break;
                }
            }
            $first = false;

            $node->addElement($this->parseExpression());
        }
        $this->stream->expect(Token::PUNCTUATION_TYPE, ']', 'An opened array is not properly closed');

        return $node;
    }

    public function parseHashExpression(): ArrayNode
    {
        $this->stream->expect(Token::PUNCTUATION_TYPE, '{', 'A hash element was expected');

        $node = new ArrayNode();
        $first = true;
        while (!$this->stream->current->test(Token::PUNCTUATION_TYPE, '}')) {
            if (!$first) {
                $this->stream->expect(Token::PUNCTUATION_TYPE, ',', 'A hash value must be followed by a comma');

                // trailing ,?
                if ($this->stream->current->test(Token::PUNCTUATION_TYPE, '}')) {
                    break;
                }
            }
            $first = false;

            // a hash key can be:

            //  * a number -- 12
            //  * a string -- 'a'
            //  * a name, which is equivalent to a string -- a
            //  * an expression, which must be enclosed in parentheses -- (1 + 2)
            if ($this->stream->current->test(Token::STRING_TYPE) || $this->stream->current->test(Token::NAME_TYPE) || $this->stream->current->test(Token::NUMBER_TYPE)) {
                $key = new ConstantNode($this->stream->current->value);
                $this->stream->next();
            } elseif ($this->stream->current->test(Token::PUNCTUATION_TYPE, '(')) {
                $key = $this->parseExpression();
            } else {
                $current = $this->stream->current;

                throw new SyntaxError(sprintf('A hash key must be a quoted string, a number, a name, or an expression enclosed in parentheses (unexpected token "%s" of value "%s"', $current->type, $current->value), $current->cursor);
            }

            $this->stream->expect(Token::PUNCTUATION_TYPE, ':', 'A hash key must be followed by a colon (:)');
            $value = $this->parseExpression();

            $node->addElement($value, $key);
        }
        $this->stream->expect(Token::PUNCTUATION_TYPE, '}', 'An opened hash is not properly closed');

        return $node;
    }

    public function parsePostfixExpression($node): Node
    {
        $token = $this->stream->current;
        while ($token->type === Token::PUNCTUATION_TYPE) {
            if ('.' === $token->value) {
                $this->stream->next();
                $token = $this->stream->current;
                $this->stream->next();

                if (
                    $token->type !== Token::NAME_TYPE
                    &&
                    // Operators like "not" and "matches" are valid method or property names,

                    // In other words, besides NAME_TYPE, OPERATOR_TYPE could also be parsed as a property or method.
                    // This is because operators are processed by the lexer prior to names. So "not" in "foo.not()" or "matches" in "foo.matches" will be recognized as an operator first.
                    // But in fact, "not" and "matches" in such expressions shall be parsed as method or property names.

                    // And this ONLY works if the operator consists of valid characters for a property or method name.

                    // Other types, such as STRING_TYPE and NUMBER_TYPE, can't be parsed as property nor method names.

                    // As a result, if $token is NOT an operator OR $token->value is NOT a valid property or method name, an exception shall be thrown.
                    ($token->type !== Token::OPERATOR_TYPE || !preg_match('/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/A', $token->value))
                ) {
                    throw new SyntaxError('Expected name', $token->cursor);
                }

                $arg = new NameNode($token->value);

                $arguments = new ArgumentsNode();
                if ($this->stream->current->test(Token::PUNCTUATION_TYPE, '(')) {
                    $type = GetAttrNode::METHOD_CALL;
                    foreach ($this->parseArguments()->nodes as $n) {
                        $arguments->addElement($n);
                    }
                } else {
                    $type = GetAttrNode::PROPERTY_CALL;
                }

                $node = new GetAttrNode($node, $arg, $arguments, $type);
            } elseif ('[' === $token->value) {
                $this->stream->next();
                $arg = $this->parseExpression();
                $this->stream->expect(Token::PUNCTUATION_TYPE, ']');

                $node = new GetAttrNode($node, $arg, new ArgumentsNode(), GetAttrNode::ARRAY_CALL);
            } else {
                break;
            }

            $token = $this->stream->current;
        }

        return $node;
    }

    /**
     * Parses arguments.
     */
    public function parseArguments(): Node
    {
        $args = array();
        $this->stream->expect(Token::PUNCTUATION_TYPE, '(', 'A list of arguments must begin with an opening parenthesis');
        while (!$this->stream->current->test(Token::PUNCTUATION_TYPE, ')')) {
            if (!empty($args)) {
                $this->stream->expect(Token::PUNCTUATION_TYPE, ',', 'Arguments must be separated by a comma');
            }

            $args[] = $this->parseExpression();
        }
        $this->stream->expect(Token::PUNCTUATION_TYPE, ')', 'A list of arguments must be closed by a parenthesis');

        return new Node($args);
    }

    /**
     * @param array $functions
     */
    public function setFunctions(array $functions): void
    {
        $this->functions = $functions;
    }
}
