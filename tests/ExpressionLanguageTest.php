<?php

namespace uuf6429\ExpressionLanguage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

class ExpressionLanguageTest extends TestCase
{
    public function testThatArrowFunctionsWorkAsExpected(): void
    {
        $el = new ExpressionLanguageWithArrowFunc();

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
            ['values']
        );
        $this->assertSame('map(function ($value) { return ($value * 2); }, $values)', $actual);

        $actual = $el->evaluate(
            'map((value) -> { value * 2}, values)',
            ['values' => [1, 3, 5, 7]]
        );
        $this->assertSame([2, 6, 10, 14], $actual);
    }
}
