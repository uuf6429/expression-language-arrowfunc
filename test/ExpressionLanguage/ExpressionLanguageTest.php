<?php

namespace uuf6429\ExpressionLanguage\Tests;

use PHPUnit\Framework\TestCase;
use uuf6429\ExpressionLanguage\ExpressionLanguage;
use uuf6429\ExpressionLanguage\SafeCallable;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

class ExpressionLanguageTest extends TestCase
{
    public function testThatArrowFunctionsWorkAsExpected(): void
    {
        $el = new ExpressionLanguage();

        $el->addFunction(
            new ExpressionFunction(
                'map',
                static function () {
                    return sprintf(
                        'map(%s)',
                        implode(', ', func_get_args())
                    );
                },
                static function ($args, SafeCallable $callback, array $array) {
                    return array_map($callback->getCallback(), $array);
                }
            )
        );

        $actual = $el->compile(
            'map((value) -> { value * 2}, values)',
            array('values')
        );
        $this->assertSame('map(function ($value) { return ($value * 2); }, $values)', $actual);

        $actual = $el->evaluate(
            'map((value) -> { value * 2}, values)',
            array('values' => array(1, 3, 5, 7))
        );
        $this->assertSame([2, 6, 10, 14], $actual);
    }

    public function testNestedArrowFunctions(): void
    {
        $el = new ExpressionLanguage();
        $el->addFunction(
            new ExpressionFunction(
                'map',
                static function () {
                    return sprintf('map(%s)', implode(', ', func_get_args()));
                },
                static function ($args, SafeCallable $callback, array $array) {
                    return array_map($callback->getCallback(), $array);
                }
            )
        );

        $actualCompile = $el->compile(
            'map((value) -> { map((v) -> { v * value }, values) }, values)',
            array('values')
        );
        $this->assertSame(
            'map(function ($value) use ($values) { return map(function ($v) use ($value) { return ($v * $value); }, $values); }, $values)',
            $actualCompile
        );

        $actualEvaluate = $el->evaluate(
            'map((value) -> { map((v) -> { v * value }, values) }, values)',
            array('values' => array(2, 3))
        );
        // Expect [[2*2, 3*2], [2*3, 3*3]] = [[4, 6], [6, 9]]
        $this->assertSame([[4, 6], [6, 9]], $actualEvaluate);
    }

    public function testLexicalScoping(): void
    {
        $el = new ExpressionLanguage();
        $el->addFunction(
            new ExpressionFunction(
                'apply',
                static function () {
                    return sprintf('apply(%s)', implode(', ', func_get_args()));
                },
                static function ($args, SafeCallable $callback, $val) {
                    return $callback->getCallback()($val);
                }
            )
        );

        $actualCompile = $el->compile(
            'apply((x) -> { x + y }, value)',
            array('y', 'value')
        );
        $this->assertSame('apply(function ($x) use ($y) { return ($x + $y); }, $value)', $actualCompile);

        $actualEvaluate = $el->evaluate(
            'apply((x) -> { x + y }, value)',
            array('y' => 10, 'value' => 5)
        );
        $this->assertSame(15, $actualEvaluate);
    }

    public function testArrowFunctionInsideStringLiterals(): void
    {
        $el = new ExpressionLanguage();
        $actualCompile = $el->compile('"some text (a) -> { a }"');
        $this->assertSame('"some text (a) -> { a }"', $actualCompile);

        $actualEvaluate = $el->evaluate('"some text (a) -> { a }"');
        $this->assertSame('some text (a) -> { a }', $actualEvaluate);
    }

    public function testMultipleParameters(): void
    {
        $el = new ExpressionLanguage();
        $el->addFunction(
            new ExpressionFunction(
                'calc',
                static function () {
                    return sprintf('calc(%s)', implode(', ', func_get_args()));
                },
                static function ($args, SafeCallable $callback, $a, $b) {
                    return $callback->getCallback()($a, $b);
                }
            )
        );

        $actualCompile = $el->compile(
            'calc((x, y) -> { x * y }, multiplier, base)',
            array('multiplier', 'base')
        );
        $this->assertSame('calc(function ($x, $y) { return ($x * $y); }, $multiplier, $base)', $actualCompile);

        $actualEvaluate = $el->evaluate(
            'calc((x, y) -> { x * y }, multiplier, base)',
            array('multiplier' => 3, 'base' => 4)
        );
        $this->assertSame(12, $actualEvaluate);
    }

    public function testNoParameters(): void
    {
        $el = new ExpressionLanguage();
        $el->addFunction(
            new ExpressionFunction(
                'run',
                static function () {
                    return sprintf('run(%s)', implode(', ', func_get_args()));
                },
                static function ($args, SafeCallable $callback) {
                    return $callback->getCallback()();
                }
            )
        );

        $actualCompile = $el->compile(
            'run(() -> { 42 })'
        );
        $this->assertSame('run(function () { return 42; })', $actualCompile);

        $actualEvaluate = $el->evaluate(
            'run(() -> { 42 })'
        );
        $this->assertSame(42, $actualEvaluate);
    }
}
