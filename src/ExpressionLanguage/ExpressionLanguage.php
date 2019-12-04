<?php

namespace uuf6429\ExpressionLanguage;

use ReflectionClass;
use ReflectionException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage as SymfonyExpressionLanguage;

class ExpressionLanguage extends SymfonyExpressionLanguage
{
    /** @var Parser */
    protected $parser;

    /**
     * {@inheritdoc}
     * @throws ReflectionException
     */
    public function __construct($cache = null, array $providers = array())
    {
        $this->parser = new Parser(array());

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
