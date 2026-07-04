<?php declare(strict_types=1);

namespace uuf6429\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage as SymfonyExpressionLanguage;
use Symfony\Component\ExpressionLanguage\ParsedExpression as SymfonyParsedExpression;

final class ExpressionLanguageWithArrowFunctions
{
	use ArrowFunctionTrait;

	/**
	 * @readonly
	 */
	private SymfonyExpressionLanguage $base;

	public function __construct(?SymfonyExpressionLanguage $base = null)
	{
		$this->base = $base ?? new SymfonyExpressionLanguage();
	}

	/**
	 * @param Expression|string $expression
	 * @param list<string> $names
	 * @api
	 */
	public function compile($expression, array $names = []): string
	{
		return $this->compileWithArrowFunctions($expression, $names);
	}

	/**
	 * @param Expression|string $expression
	 * @param array<string, mixed> $values
	 * @return mixed
	 * @api
	 */
	public function evaluate($expression, array $values = [])
	{
		return $this->evaluateWithArrowFunctions($expression, $values);
	}

	/**
	 * @param Expression|string $expression
	 * @param list<string> $names
	 * @api
	 */
	public function parse($expression, array $names): ParsedExpression
	{
		return $this->parseWithArrowFunctions($expression, $names);
	}

	/**
	 * @param Expression|string $expression
	 * @param list<string> $names
	 * @api
	 */
	public function lint($expression, array $names): void
	{
		$this->lintWithArrowFunctions($expression, $names);
	}

	/**
	 * @api
	 */
	public function register(string $name, callable $compiler, callable $evaluator): void
	{
		$this->base->register($name, $compiler, $evaluator);
	}

	/**
	 * @api
	 */
	public function addFunction(ExpressionFunction $function): void
	{
		$this->base->addFunction($function);
	}

	/**
	 * @api
	 */
	public function registerProvider(ExpressionFunctionProviderInterface $provider): void
	{
		$this->base->registerProvider($provider);
	}

	#[\Override]
	protected function compileWithoutArrowFunctions($expression, array $names = []): string
	{
		return $this->base->compile($expression, $names);
	}

	#[\Override]
	protected function evaluateWithoutArrowFunctions($expression, array $values = [])
	{
		return $this->base->evaluate($expression, $values);
	}

	#[\Override]
	protected function parseWithoutArrowFunctions($expression, array $names = []): SymfonyParsedExpression
	{
		return $this->base->parse($expression, $names);
	}

	#[\Override]
	protected function lintWithoutArrowFunctions($expression, array $names = []): void
	{
		$this->base->lint($expression, $names);
	}
}
