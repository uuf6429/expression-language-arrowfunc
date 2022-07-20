<?php

namespace uuf6429\ExpressionLanguage\Node;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\Compiler;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use uuf6429\ExpressionLanguage\SafeCallable;

class ArrowFuncNodeTest extends TestCase
{
    /**
     * @param $expectedResult
     * @param ArrowFuncNode $node
     * @param array $variables
     * @param array $functions
     *
     * @dataProvider getEvaluateData
     */
    public function testEvaluate($expectedResult, ArrowFuncNode $node, array $variables = [], array $functions = []): void
    {
        $safeCallback = $node->evaluate($functions, $variables);
        $this->assertInstanceOf(SafeCallable::class, $safeCallback);
        $this->assertIsCallable($safeCallback->getCallback());

        $actualResult = $safeCallback->callArray($variables);
        $this->assertSame($expectedResult, $actualResult);
    }

    public function getEvaluateData(): array
    {
        return [
            'parameterless call, null result' => [
                null,
                new ArrowFuncNode(
                    [],
                    null
                ),
            ],
            'one parameter, returned' => [
                123,
                new ArrowFuncNode(
                    [new NameNode('foo')],
                    new NameNode('foo')
                ),
                ['foovalue' => 123],
            ],
            'two parameters, multiplied and result returned' => [
                246,
                new ArrowFuncNode(
                    [new NameNode('foo'), new NameNode('bar')],
                    new BinaryNode('*', new NameNode('foo'), new NameNode('bar'))
                ),
                ['foovalue' => 123, 'barvalue' => 2],
            ],
            'one unused parameter, returns literal' => [
                890,
                new ArrowFuncNode(
                    [new NameNode('foo')],
                    new ConstantNode(890)
                ),
                ['foovalue' => 123],
            ],
        ];
    }

    /**
     * @param string $expected
     * @param ArrowFuncNode $node
     * @param array $functions
     *
     * @dataProvider getCompileData
     */
    public function testCompile(string $expected, ArrowFuncNode $node, array $functions = []): void
    {
        $compiler = new Compiler($functions);
        $node->compile($compiler);
        $this->assertSame($expected, $compiler->getSource());
    }

    public function getCompileData(): array
    {
        return [
            [
                'function () { return null; }',
                new ArrowFuncNode(
                    [],
                    null
                ),
            ],
            [
                'function ($foo) { return $foo; }',
                new ArrowFuncNode(
                    [new NameNode('foo')],
                    new NameNode('foo')
                ),
            ],
            [
                'function ($foo, $bar) { return ($foo * $bar); }',
                new ArrowFuncNode(
                    [new NameNode('foo'), new NameNode('bar')],
                    new BinaryNode('*', new NameNode('foo'), new NameNode('bar'))
                ),
            ],
        ];
    }

    /**
     * @param string $expected
     * @param ArrowFuncNode $node
     *
     * @dataProvider getDumpData
     */
    public function testDump(string $expected, ArrowFuncNode $node): void
    {
        $this->assertSame($expected, $node->dump());
    }

    public function getDumpData(): array
    {
        return [
            [
                '() -> {}',
                new ArrowFuncNode(
                    [],
                    null
                ),
            ],
            [
                '(foo) -> {foo}',
                new ArrowFuncNode(
                    [new NameNode('foo')],
                    new NameNode('foo')
                ),
            ],
            [
                '(foo) -> {"bar"}',
                new ArrowFuncNode(
                    [new NameNode('foo')],
                    new ConstantNode('bar')
                ),
            ],
            [
                '(foo, bar) -> {(foo * bar)}',
                new ArrowFuncNode(
                    [new NameNode('foo'), new NameNode('bar')],
                    new BinaryNode('*', new NameNode('foo'), new NameNode('bar'))
                ),
            ],
        ];
    }
}
