<?php declare(strict_types=1);

namespace uuf6429\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\ParsedExpression as SymfonyParsedExpression;

class ParsedExpression extends SymfonyParsedExpression
{
	/**
	 * @var array<string, array{params: list<string>, body: string}>
	 */
	private array $lambdas;

	/**
	 * @param string $expression
	 * @param Node $nodes
	 * @param array<string, array{params: list<string>, body: string}> $lambdas
	 */
	public function __construct(string $expression, Node $nodes, array $lambdas)
	{
		parent::__construct($expression, $nodes);
		$this->lambdas = $lambdas;
	}

	/**
	 * @return array<string, array{params: list<string>, body: string}>
	 */
	public function getLambdas(): array
	{
		return $this->lambdas;
	}
}
