<?php

namespace uuf6429\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage as SymfonyExpressionLanguage;

class ExpressionLanguage extends SymfonyExpressionLanguage
{
    use ArrowFunctionTrait;

    public function evaluate($expression, $values = [])
    {
        return $this->evaluateWithArrowFunctions($expression, $values);
    }

    public function compile($expression, $names = []): string
    {
        return $this->compileWithArrowFunctions($expression, $names);
    }
}
