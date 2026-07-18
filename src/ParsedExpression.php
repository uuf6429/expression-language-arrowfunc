<?php declare(strict_types=1);

namespace uuf6429\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\Node\Node;
use Symfony\Component\ExpressionLanguage\ParsedExpression as SymfonyParsedExpression;

/**
 * @phpstan-type TLambdaName non-empty-string
 * @phpstan-type TLambdaDefinition array{
 *     params: list<string>,
 *     body: string,
 *     fromChar: non-negative-int,
 *     untilChar: non-negative-int,
 * }
 * @phpstan-type TLambdas array<TLambdaName, TLambdaDefinition>
 */
class ParsedExpression extends SymfonyParsedExpression
{
	/**
	 * @var TLambdas
	 */
	private array $lambdas;

	/**
	 * @param string $expression
	 * @param Node $nodes
	 * @param TLambdas $lambdas
	 */
	public function __construct(string $expression, Node $nodes, array $lambdas)
	{
		parent::__construct($expression, $nodes);
		$this->lambdas = $lambdas;
	}

	/**
	 * @return TLambdas
	 */
	public function getLambdas(): array
	{
		return $this->lambdas;
	}
}
