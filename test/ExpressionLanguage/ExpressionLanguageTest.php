<?php

namespace uuf6429\ExpressionLanguage\Tests;

use uuf6429\ExpressionLanguage\ExpressionLanguage;
use uuf6429\ExpressionLanguage\SafeCallable;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;

class ExpressionLanguageTest extends \PHPUnit_Framework_TestCase
{
    public function testThatArrowFunctionsWorkAsExpected()
    {
        $el = new ExpressionLanguage();

        $el->addFunction(
            new ExpressionFunction(
                'map',
                function () {
                    return sprintf(
                        'map(%s)',
                        implode(', ', func_get_args())
                    );
                },
                function ($args, SafeCallable $callback, array $array) {
                    return array_map($callback->getCallback(), $array);
                }
            )
        );

        $actual = $el->evaluate(
            'map((value) -> { value * 2}, values)',
            array('values' => array(1, 3, 5, 7))
        );

        $this->assertSame([2, 6, 10, 14], $actual);
    }
}
