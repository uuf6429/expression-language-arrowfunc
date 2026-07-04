<?php declare(strict_types=1);

namespace uuf6429\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage as SymfonyExpressionLanguage;

final class ExpressionLanguage extends SymfonyExpressionLanguage
{
	use ArrowFunctionTrait {
		compileWithArrowFunctions as private internalCompileWithArrowFunctions;
		evaluateWithArrowFunctions as private internalEvaluateWithArrowFunctions;
	}

	/**
	 * @param Expression|string $expression
	 * @param list<string> $names
	 */
	public function compileWithArrowFunctions($expression, array $names = []): string
	{
		return $this->internalCompileWithArrowFunctions($expression, $names);
	}

	/**
	 * @param Expression|string $expression
	 * @param array<string, mixed> $values
	 * @return mixed
	 */
	public function evaluateWithArrowFunctions($expression, array $values = [])
	{
		return $this->internalEvaluateWithArrowFunctions($expression, $values);
	}
}
