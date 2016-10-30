<?php

namespace uuf6429\ExpressionLanguage\Tests;

use uuf6429\ExpressionLanguage\Node\ArrowFuncNode;
use uuf6429\ExpressionLanguage\SafeCallable;
use Symfony\Component\ExpressionLanguage\Node\BinaryNode;
use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\ConstantNode;

class ArrowFuncNodeTest extends \Symfony\Component\ExpressionLanguage\Tests\Node\AbstractNodeTest
{
    /**
     * @dataProvider getEvaluateData
     */
    public function testEvaluate($expectedResult, $node, $variables = array(), $functions = array())
    {
        $safeCallback = $node->evaluate($functions, $variables);
        $this->assertInstanceOf(SafeCallable::class, $safeCallback);
        $this->assertTrue(is_callable($safeCallback->getCallback()));

        $actualResult = $safeCallback->callArray($variables);
        $this->assertSame($expectedResult, $actualResult);
    }

    public function getEvaluateData()
    {
        return array(
            'parameterless call, null result' => array(
                null,
                new ArrowFuncNode(
                    array(),
                    null
                ),
            ),
            'one parameter, returned' => array(
                123,
                new ArrowFuncNode(
                    array(new NameNode('foo')),
                    new NameNode('foo')
                ),
                array('foovalue' => 123),
            ),
            'two parameters, multiplied and result returned' => array(
                246,
                new ArrowFuncNode(
                    array(new NameNode('foo'), new NameNode('bar')),
                    new BinaryNode('*', new NameNode('foo'), new NameNode('bar'))
                ),
                array('foovalue' => 123, 'barvalue' => 2),
            ),
            'one unused parameter, returns literal' => array(
                890,
                new ArrowFuncNode(
                    array(new NameNode('foo')),
                    new ConstantNode(890)
                ),
                array('foovalue' => 123),
            ),
        );
    }

    public function getCompileData()
    {
        return array(
            array(
                'function () { return null; }',
                new ArrowFuncNode(
                    array(),
                    null
                ),
            ),
            array(
                'function ($foo) { return $foo; }',
                new ArrowFuncNode(
                    array(new NameNode('foo')),
                    new NameNode('foo')
                ),
            ),
            array(
                'function ($foo, $bar) { return ($foo * $bar); }',
                new ArrowFuncNode(
                    array(new NameNode('foo'), new NameNode('bar')),
                    new BinaryNode('*', new NameNode('foo'), new NameNode('bar'))
                ),
            ),
        );
    }

    public function getDumpData()
    {
        return array(
            array(
                '() -> {}',
                new ArrowFuncNode(
                    array(),
                    null
                ),
            ),
            array(
                '(foo) -> {foo}',
                new ArrowFuncNode(
                    array(new NameNode('foo')),
                    new NameNode('foo')
                ),
            ),
            array(
                '(foo) -> {"bar"}',
                new ArrowFuncNode(
                    array(new NameNode('foo')),
                    new ConstantNode('bar')
                ),
            ),
            array(
                '(foo, bar) -> {(foo * bar)}',
                new ArrowFuncNode(
                    array(new NameNode('foo'), new NameNode('bar')),
                    new BinaryNode('*', new NameNode('foo'), new NameNode('bar'))
                ),
            ),
        );
    }
}
