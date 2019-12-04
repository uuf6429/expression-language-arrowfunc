<?php

namespace uuf6429\ExpressionLanguage\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use uuf6429\ExpressionLanguage\Parser;
use uuf6429\ExpressionLanguage\Lexer;
use uuf6429\ExpressionLanguage\Node\ArrowFuncNode;
use Symfony\Component\ExpressionLanguage\Node;

class ParserTest extends TestCase
{
    public function testParseWithInvalidName(): void
    {
        $lexer = new Lexer();
        $parser = new Parser(array());

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Variable "foo" is not valid around position 1.');

        $parser->parse($lexer->tokenize('foo'));
    }

    public function testParseWithZeroInNames()
    {
        $lexer = new Lexer();
        $parser = new Parser(array());

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Variable "foo" is not valid around position 1.');

        $parser->parse($lexer->tokenize('foo'), array(0));
    }

    /**
     * @param $node
     * @param $expression
     * @param array $names
     * @param array $funcs
     * @dataProvider getParseData
     */
    public function testParse($node, $expression, $names = [], $funcs = []): void
    {
        $lexer = new Lexer();
        $parser = new Parser($funcs);
        $this->assertEquals($node, $parser->parse($lexer->tokenize($expression), $names));
    }

    public function getParseData(): array
    {
        $arguments = new Node\ArgumentsNode();
        $arguments->addElement(new Node\ConstantNode('arg1'));
        $arguments->addElement(new Node\ConstantNode(2));
        $arguments->addElement(new Node\ConstantNode(true));

        return array(
            array(
                new Node\NameNode('a'),
                'a',
                array('a'),
            ),
            array(
                new Node\ConstantNode('a'),
                '"a"',
            ),
            array(
                new Node\ConstantNode(3),
                '3',
            ),
            array(
                new Node\ConstantNode(false),
                'false',
            ),
            array(
                new Node\ConstantNode(true),
                'true',
            ),
            array(
                new Node\ConstantNode(null),
                'null',
            ),
            array(
                new Node\UnaryNode('-', new Node\ConstantNode(3)),
                '-3',
            ),
            array(
                new Node\BinaryNode('-', new Node\ConstantNode(3), new Node\ConstantNode(3)),
                '3 - 3',
            ),
            array(
                new Node\BinaryNode('*',
                    new Node\BinaryNode('-', new Node\ConstantNode(3), new Node\ConstantNode(3)),
                    new Node\ConstantNode(2)
                ),
                '(3 - 3) * 2',
            ),
            array(
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\NameNode('bar'), new Node\ArgumentsNode(), Node\GetAttrNode::PROPERTY_CALL),
                'foo.bar',
                array('foo'),
            ),
            array(
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\NameNode('bar'), new Node\ArgumentsNode(), Node\GetAttrNode::METHOD_CALL),
                'foo.bar()',
                array('foo'),
            ),
            array(
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\NameNode('not'), new Node\ArgumentsNode(), Node\GetAttrNode::METHOD_CALL),
                'foo.not()',
                array('foo'),
            ),
            array(
                new Node\GetAttrNode(
                    new Node\NameNode('foo'),
                    new Node\NameNode('bar'),
                    $arguments,
                    Node\GetAttrNode::METHOD_CALL
                ),
                'foo.bar("arg1", 2, true)',
                array('foo'),
            ),
            array(
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode(3), new Node\ArgumentsNode(), Node\GetAttrNode::ARRAY_CALL),
                'foo[3]',
                array('foo'),
            ),
            array(
                new Node\ConditionalNode(new Node\ConstantNode(true), new Node\ConstantNode(true), new Node\ConstantNode(false)),
                'true ? true : false',
            ),
            array(
                new Node\BinaryNode('matches', new Node\ConstantNode('foo'), new Node\ConstantNode('/foo/')),
                '"foo" matches "/foo/"',
            ),

            // chained calls
            array(
                $this->createGetAttrNode(
                    $this->createGetAttrNode(
                        $this->createGetAttrNode(
                            $this->createGetAttrNode(new Node\NameNode('foo'), 'bar', Node\GetAttrNode::METHOD_CALL),
                            'foo', Node\GetAttrNode::METHOD_CALL),
                        'baz', Node\GetAttrNode::PROPERTY_CALL),
                    '3', Node\GetAttrNode::ARRAY_CALL),
                'foo.bar().foo().baz[3]',
                array('foo'),
            ),

            array(
                new Node\NameNode('foo'),
                'bar',
                array('foo' => 'bar'),
            ),

            array(
                new ArrowFuncNode(
                    array(
                        new Node\NameNode('foo'),
                        new Node\NameNode('bar'),
                    ),
                    new Node\BinaryNode(
                        '*',
                        new Node\NameNode('foo'),
                        new Node\NameNode('bar')
                    )
                ),
                '(foo, bar) -> { foo * bar }',
            ),

            array(
                new ArrowFuncNode(
                    array(
                        new Node\NameNode('foo'),
                        new Node\NameNode('bars'),
                    ),
                    new Node\BinaryNode(
                        '*',
                        new Node\NameNode('foo'),
                        new Node\FunctionNode(
                            'map',
                            new Node\Node(
                                array(
                                    new Node\NameNode('bars'),
                                    new ArrowFuncNode(
                                        array(
                                            new Node\NameNode('bar'),
                                        ),
                                        new Node\BinaryNode(
                                            '*',
                                            new Node\NameNode('bar'),
                                            new Node\NameNode('baz')
                                        )
                                    ),
                                )
                            )
                        )
                    )
                ),
                '(foo, bars) -> { foo * map(bars, (bar) -> { bar * baz }) }',
                array('baz'),
                array('map' => 'array_map'),
            ),
        );
    }

    private function createGetAttrNode($node, $item, $type): Node\GetAttrNode
    {
        $attr = Node\GetAttrNode::ARRAY_CALL === $type ? new Node\ConstantNode($item) : new Node\NameNode($item);

        return new Node\GetAttrNode($node, $attr, new Node\ArgumentsNode(), $type);
    }

    /**
     * @dataProvider getInvalidPostfixData
     */
    public function testParseWithInvalidPostfixData($expr, $names = array())
    {
        $lexer = new Lexer();
        $parser = new Parser(array());

        $this->expectException(SyntaxError::class);

        $parser->parse($lexer->tokenize($expr), $names);
    }

    public function getInvalidPostfixData(): array
    {
        return array(
            array(
                'foo."#"',
                array('foo'),
            ),
            array(
                'foo."bar"',
                array('foo'),
            ),
            array(
                'foo.**',
                array('foo'),
            ),
            array(
                'foo.123',
                array('foo'),
            ),
        );
    }
}
