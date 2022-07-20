<?php

namespace uuf6429\ExpressionLanguage;

use ReflectionClass;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage as SymfonyExpressionLanguage;

class ExpressionLanguageWithArrowFunc extends SymfonyExpressionLanguage
{
    /** @var Parser */
    protected $parser;

    public function __construct($cache = null, array $providers = [])
    {
        $this->parser = new Parser([]);

        parent::__construct($cache, $providers);

        $reflection = new ReflectionClass(SymfonyExpressionLanguage::class);

        $prop = $reflection->getProperty('lexer');
        $prop->setAccessible(true);
        $prop->setValue($this, new Lexer());
        $prop->setAccessible(false);

        $prop = $reflection->getProperty('parser');
        $prop->setAccessible(true);
        $prop->setValue($this, $this->parser);
        $prop->setAccessible(false);
    }

    /**
     * Hack to keep functions in parser up to date.
     *
     * {@inheritdoc}
     */
    public function register($name, callable $compiler, callable $evaluator): void
    {
        $this->functions[$name] = ['compiler' => $compiler, 'evaluator' => $evaluator];
        $this->parser->setFunctions($this->functions);
    }
}
