<?php declare(strict_types=1);

namespace uuf6429\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage as SymfonyExpressionLanguage;

final class ExpressionLanguage extends SymfonyExpressionLanguage
{
	use ArrowFunctionTrait;

	/**
	 * @param Expression|string $expression
	 * @param list<string> $names
	 */
	public function compile($expression, $names = []): string
	{
		return $this->compileWithArrowFunctions($expression, $names);
	}

	/**
	 * @param Expression|string $expression
	 * @param array<string, mixed> $values
	 * @return mixed
	 */
	public function evaluate($expression, $values = [])
	{
		return $this->evaluateWithArrowFunctions($expression, $values);
	}
}
