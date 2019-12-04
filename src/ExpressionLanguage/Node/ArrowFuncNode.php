<?php

namespace uuf6429\ExpressionLanguage\Node;

use Symfony\Component\ExpressionLanguage\Node\NameNode;
use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\Compiler;
use uuf6429\ExpressionLanguage\SafeCallable;

/**
 * @author Christian Sciberras <christian@sciberras.me>
 *
 * @internal
 */
class ArrowFuncNode extends Node
{
    /**
     * @var SafeCallable
     */
    private static $noopSafeCallable;

    /**
     * @param NameNode[] $parameters
     * @param Node|null $body
     */
    public function __construct(array $parameters, Node $body = null)
    {
        parent::__construct(
            array(
                'parameters' => $parameters,
                'body' => $body,
            )
        );

        if (!self::$noopSafeCallable) {
            self::$noopSafeCallable = new SafeCallable(static function () {
            });
        }
    }

    public function compile(Compiler $compiler): void
    {
        $arguments = array();

        foreach ($this->nodes['parameters'] as $parameterNode) {
            $arguments[] = $compiler->subcompile($parameterNode);
        }

        $compiler->raw(
            sprintf(
                'function (%s) { return %s; }',
                implode(', ', $arguments),
                $this->nodes['body'] ? $compiler->subcompile($this->nodes['body']) : 'null'
            )
        );
    }

    public function evaluate($functions, $values)
    {
        /** @var Node|null $bodyNode */
        $bodyNode = $this->nodes['body'];

        if (!$bodyNode) {
            return self::$noopSafeCallable;
        }

        $paramNames = array();

        foreach ($this->nodes['parameters'] as $parameterNode) {
            /** @var NameNode $parameterNode */
            $nodeData = $parameterNode->toArray();
            $paramNames[] = $nodeData[0];
        }

        return new SafeCallable(
            static function () use ($functions, $paramNames, $bodyNode) {
                $passedValues = array_combine($paramNames, func_get_args());

                return $bodyNode->evaluate($functions, $passedValues);
            }
        );
    }

    public function toArray()
    {
        $array = array();

        foreach ($this->nodes['parameters'] as $node) {
            $array[] = ', ';
            $array[] = $node;
        }
        $array[0] = '(';
        $array[] = ') -> {';
        if ($this->nodes['body']) {
            $array[] = $this->nodes['body'];
        }
        $array[] = '}';

        return $array;
    }
}
