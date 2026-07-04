<?php

namespace uuf6429\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage as SymfonyExpressionLanguage;

class ExpressionLanguage extends SymfonyExpressionLanguage
{
    use ArrowFunctionTrait;

    /**
     * {@inheritdoc}
     */
    public function evaluate($expression, $values = array())
    {
        return $this->evaluateWithArrows($expression, $values);
    }

    /**
     * {@inheritdoc}
     */
    public function compile($expression, $names = array())
    {
        return $this->compileWithArrows($expression, $names);
    }
}
