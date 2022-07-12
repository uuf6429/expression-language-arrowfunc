<?php

namespace uuf6429\ExpressionLanguage;

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
}
