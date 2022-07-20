<?php

namespace uuf6429\ExpressionLanguage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Node;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use uuf6429\ExpressionLanguage\Node\ArrowFuncNode;

class ParserTest extends TestCase
{
    public function testParseWithInvalidName(): void
    {
        $lexer = new Lexer();
        $parser = new Parser([]);

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Variable "foo" is not valid around position 1.');

        $parser->parse($lexer->tokenize('foo'));
    }

    public function testParseWithZeroInNames(): void
    {
        $lexer = new Lexer();
        $parser = new Parser([]);

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Variable "foo" is not valid around position 1.');

        $parser->parse($lexer->tokenize('foo'), [0]);
    }

    /**
     * @param $node
     * @param $expression
     * @param array $names
     * @param array $funcs
     * @dataProvider getParseData
     */
    public function testParse($node, $expression, array $names = [], array $funcs = []): void
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

        return [
            [
                new Node\NameNode('a'),
                'a',
                ['a'],
            ],
            [
                new Node\ConstantNode('a'),
                '"a"',
            ],
            [
                new Node\ConstantNode(3),
                '3',
            ],
            [
                new Node\ConstantNode(false),
                'false',
            ],
            [
                new Node\ConstantNode(true),
                'true',
            ],
            [
                new Node\ConstantNode(null),
                'null',
            ],
            [
                new Node\UnaryNode('-', new Node\ConstantNode(3)),
                '-3',
            ],
            [
                new Node\BinaryNode('-', new Node\ConstantNode(3), new Node\ConstantNode(3)),
                '3 - 3',
            ],
            [
                new Node\BinaryNode('*',
                    new Node\BinaryNode('-', new Node\ConstantNode(3), new Node\ConstantNode(3)),
                    new Node\ConstantNode(2)
                ),
                '(3 - 3) * 2',
            ],
            [
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\NameNode('bar'), new Node\ArgumentsNode(), Node\GetAttrNode::PROPERTY_CALL),
                'foo.bar',
                ['foo'],
            ],
            [
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\NameNode('bar'), new Node\ArgumentsNode(), Node\GetAttrNode::METHOD_CALL),
                'foo.bar()',
                ['foo'],
            ],
            [
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\NameNode('not'), new Node\ArgumentsNode(), Node\GetAttrNode::METHOD_CALL),
                'foo.not()',
                ['foo'],
            ],
            [
                new Node\GetAttrNode(
                    new Node\NameNode('foo'),
                    new Node\NameNode('bar'),
                    $arguments,
                    Node\GetAttrNode::METHOD_CALL
                ),
                'foo.bar("arg1", 2, true)',
                ['foo'],
            ],
            [
                new Node\GetAttrNode(new Node\NameNode('foo'), new Node\ConstantNode(3), new Node\ArgumentsNode(), Node\GetAttrNode::ARRAY_CALL),
                'foo[3]',
                ['foo'],
            ],
            [
                new Node\ConditionalNode(new Node\ConstantNode(true), new Node\ConstantNode(true), new Node\ConstantNode(false)),
                'true ? true : false',
            ],
            [
                new Node\BinaryNode('matches', new Node\ConstantNode('foo'), new Node\ConstantNode('/foo/')),
                '"foo" matches "/foo/"',
            ],

            // chained calls
            [
                $this->createGetAttrNode(
                    $this->createGetAttrNode(
                        $this->createGetAttrNode(
                            $this->createGetAttrNode(new Node\NameNode('foo'), 'bar', Node\GetAttrNode::METHOD_CALL),
                            'foo', Node\GetAttrNode::METHOD_CALL),
                        'baz', Node\GetAttrNode::PROPERTY_CALL),
                    '3', Node\GetAttrNode::ARRAY_CALL),
                'foo.bar().foo().baz[3]',
                ['foo'],
            ],

            [
                new Node\NameNode('foo'),
                'bar',
                ['foo' => 'bar'],
            ],

            [
                new ArrowFuncNode(
                    [
                        new Node\NameNode('foo'),
                        new Node\NameNode('bar'),
                    ],
                    new Node\BinaryNode(
                        '*',
                        new Node\NameNode('foo'),
                        new Node\NameNode('bar')
                    )
                ),
                '(foo, bar) -> { foo * bar }',
            ],

            [
                new ArrowFuncNode(
                    [
                        new Node\NameNode('foo'),
                        new Node\NameNode('bars'),
                    ],
                    new Node\BinaryNode(
                        '*',
                        new Node\NameNode('foo'),
                        new Node\FunctionNode(
                            'map',
                            new Node\Node(
                                [
                                    new Node\NameNode('bars'),
                                    new ArrowFuncNode(
                                        [
                                            new Node\NameNode('bar'),
                                        ],
                                        new Node\BinaryNode(
                                            '*',
                                            new Node\NameNode('bar'),
                                            new Node\NameNode('baz')
                                        )
                                    ),
                                ]
                            )
                        )
                    )
                ),
                '(foo, bars) -> { foo * map(bars, (bar) -> { bar * baz }) }',
                ['baz'],
                ['map' => 'array_map'],
            ],
        ];
    }

    private function createGetAttrNode($node, $item, $type): Node\GetAttrNode
    {
        $attr = Node\GetAttrNode::ARRAY_CALL === $type ? new Node\ConstantNode($item) : new Node\NameNode($item);

        return new Node\GetAttrNode($node, $attr, new Node\ArgumentsNode(), $type);
    }

    /**
     * @dataProvider getInvalidPostfixData
     */
    public function testParseWithInvalidPostfixData($expr, $names = []): void
    {
        $lexer = new Lexer();
        $parser = new Parser([]);

        $this->expectException(SyntaxError::class);

        $parser->parse($lexer->tokenize($expr), $names);
    }

    public function getInvalidPostfixData(): array
    {
        return [
            [
                'foo."#"',
                ['foo'],
            ],
            [
                'foo."bar"',
                ['foo'],
            ],
            [
                'foo.**',
                ['foo'],
            ],
            [
                'foo.123',
                ['foo'],
            ],
        ];
    }
}
