<?php declare(strict_types=1);

namespace uuf6429\ExpressionLanguageTests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use uuf6429\ExpressionLanguage\ExpressionLanguage;
use uuf6429\ExpressionLanguage\SafeCallable;

/**
 * @internal
 */
final class ExpressionLanguageTest extends TestCase
{
	public function testThatArrowFunctionsWorkAsExpected(): void
	{
		$el = new ExpressionLanguage();

		$el->addFunction(
			new ExpressionFunction(
				'map',
				static function (string ...$expressions) {
					return sprintf('map(%s)', implode(', ', $expressions));
				},
				static function ($args, SafeCallable $callback, array $array) {
					return array_map($callback->getCallback(), $array);
				}
			)
		);

		$actualCompileResult = $el->compileWithArrowFunctions(
			'map((value) -> { value * 2}, values)',
			['values']
		);
		$actualEvaluateResult = $el->evaluateWithArrowFunctions(
			'map((value) -> { value * 2}, values)',
			['values' => [1, 3, 5, 7]]
		);

		$this->assertSame('map(function ($value) { return ($value * 2); }, $values)', $actualCompileResult);
		$this->assertSame([2, 6, 10, 14], $actualEvaluateResult);
	}

	public function testNestedArrowFunctions(): void
	{
		$el = new ExpressionLanguage();
		$el->addFunction(
			new ExpressionFunction(
				'map',
				static function (string ...$expressions) {
					return sprintf('map(%s)', implode(', ', $expressions));
				},
				static function ($args, SafeCallable $callback, array $array) {
					return array_map($callback->getCallback(), $array);
				}
			)
		);

		$actualCompileResult = $el->compileWithArrowFunctions(
			'map((value) -> { map((v) -> { v * value }, values) }, values)',
			['values']
		);
		$actualEvaluateResult = $el->evaluateWithArrowFunctions(
			'map((value) -> { map((v) -> { v * value }, values) }, values)',
			['values' => [2, 3]]
		);

		$this->assertSame(
			'map(function ($value) use ($values) { return map(function ($v) use ($value) { return ($v * $value); }, $values); }, $values)',
			$actualCompileResult
		);
		$this->assertSame([[4, 6], [6, 9]], $actualEvaluateResult);
	}

	public function testLexicalScoping(): void
	{
		$el = new ExpressionLanguage();
		$el->addFunction(
			new ExpressionFunction(
				'apply',
				static function (string ...$expressions) {
					return sprintf('apply(%s)', implode(', ', $expressions));
				},
				static function ($args, SafeCallable $callback, $val) {
					return $callback->getCallback()($val);
				}
			)
		);

		$actualCompileResult = $el->compileWithArrowFunctions(
			'apply((x) -> { x + y }, value)',
			['y', 'value']
		);
		$actualEvaluateResult = $el->evaluateWithArrowFunctions(
			'apply((x) -> { x + y }, value)',
			['y' => 10, 'value' => 5]
		);

		$this->assertSame('apply(function ($x) use ($y) { return ($x + $y); }, $value)', $actualCompileResult);
		$this->assertSame(15, $actualEvaluateResult);
	}

	public function testArrowFunctionInsideStringLiterals(): void
	{
		$el = new ExpressionLanguage();

		$actualCompileResult = $el->compileWithArrowFunctions('"some text (a) -> { a }"');
		$actualEvaluateResult = $el->evaluateWithArrowFunctions('"some text (a) -> { a }"');

		$this->assertSame('"some text (a) -> { a }"', $actualCompileResult);
		$this->assertSame('some text (a) -> { a }', $actualEvaluateResult);
	}

	public function testMultipleParameters(): void
	{
		$el = new ExpressionLanguage();
		$el->addFunction(
			new ExpressionFunction(
				'calc',
				static function (string ...$expressions) {
					return sprintf('calc(%s)', implode(', ', $expressions));
				},
				static function ($args, SafeCallable $callback, $a, $b) {
					return $callback->getCallback()($a, $b);
				}
			)
		);

		$actualCompileResult = $el->compileWithArrowFunctions(
			'calc((x, y) -> { x * y }, multiplier, base)',
			['multiplier', 'base']
		);
		$actualEvaluateResult = $el->evaluateWithArrowFunctions(
			'calc((x, y) -> { x * y }, multiplier, base)',
			['multiplier' => 3, 'base' => 4]
		);

		$this->assertSame('calc(function ($x, $y) { return ($x * $y); }, $multiplier, $base)', $actualCompileResult);
		$this->assertSame(12, $actualEvaluateResult);
	}

	public function testNoParameters(): void
	{
		$el = new ExpressionLanguage();
		$el->addFunction(
			new ExpressionFunction(
				'run',
				static function (string ...$expressions) {
					return sprintf('run(%s)', implode(', ', $expressions));
				},
				static function ($args, SafeCallable $callback) {
					return $callback->getCallback()();
				}
			)
		);

		$actualCompileResult = $el->compileWithArrowFunctions('run(() -> { 42 })');
		$actualEvaluateResult = $el->evaluateWithArrowFunctions('run(() -> { 42 })');

		$this->assertSame('run(function () { return 42; })', $actualCompileResult);
		$this->assertSame(42, $actualEvaluateResult);
	}
}
