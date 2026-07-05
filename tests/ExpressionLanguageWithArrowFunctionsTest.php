<?php declare(strict_types=1);

namespace uuf6429\ExpressionLanguageTests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use uuf6429\ExpressionLanguage\ExpressionLanguageWithArrowFunctions;
use uuf6429\ExpressionLanguage\SafeCallable;

/**
 * @internal
 */
final class ExpressionLanguageWithArrowFunctionsTest extends TestCase
{
	public function testThatArrowFunctionsWorkAsExpected(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();

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

		$actualCompileResult = $el->compile(
			'map((value) -> { value * 2}, values)',
			['values']
		);
		$actualEvaluateResult = $el->evaluate(
			'map((value) -> { value * 2}, values)',
			['values' => [1, 3, 5, 7]]
		);

		$this->assertSame('map(function ($value) { return ($value * 2); }, $values)', $actualCompileResult);
		$this->assertSame([2, 6, 10, 14], $actualEvaluateResult);
	}

	public function testNestedArrowFunctions(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
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

		$actualCompileResult = $el->compile(
			'map((value) -> { map((v) -> { v * value }, values) }, values)',
			['values']
		);
		$actualEvaluateResult = $el->evaluate(
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
		$el = new ExpressionLanguageWithArrowFunctions();
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

		$actualCompileResult = $el->compile(
			'apply((x) -> { x + y }, value)',
			['y', 'value']
		);
		$actualEvaluateResult = $el->evaluate(
			'apply((x) -> { x + y }, value)',
			['y' => 10, 'value' => 5]
		);

		$this->assertSame('apply(function ($x) use ($y) { return ($x + $y); }, $value)', $actualCompileResult);
		$this->assertSame(15, $actualEvaluateResult);
	}

	public function testArrowFunctionInsideStringLiterals(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();

		$actualCompileResult = $el->compile('"some text (a) -> { a }"');
		$actualEvaluateResult = $el->evaluate('"some text (a) -> { a }"');

		$this->assertSame('"some text (a) -> { a }"', $actualCompileResult);
		$this->assertSame('some text (a) -> { a }', $actualEvaluateResult);
	}

	public function testMultipleParameters(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
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

		$actualCompileResult = $el->compile(
			'calc((x, y) -> { x * y }, multiplier, base)',
			['multiplier', 'base']
		);
		$actualEvaluateResult = $el->evaluate(
			'calc((x, y) -> { x * y }, multiplier, base)',
			['multiplier' => 3, 'base' => 4]
		);

		$this->assertSame('calc(function ($x, $y) { return ($x * $y); }, $multiplier, $base)', $actualCompileResult);
		$this->assertSame(12, $actualEvaluateResult);
	}

	public function testNoParameters(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
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

		$actualCompileResult = $el->compile('run(() -> { 42 })');
		$actualEvaluateResult = $el->evaluate('run(() -> { 42 })');

		$this->assertSame('run(function () { return 42; })', $actualCompileResult);
		$this->assertSame(42, $actualEvaluateResult);
	}

	public function testParseWithArrowFunctions(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
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

		$parsed = $el->parse('map((value) -> { value * 2 }, values)', ['values']);

		$this->assertSame('map((value) -> { value * 2 }, values)', (string)$parsed);

		$lambdas = $parsed->getLambdas();
		$this->assertCount(1, $lambdas);
		$this->assertArrayHasKey('__lambda_0', $lambdas);
		$this->assertSame(['value'], $lambdas['__lambda_0']['params']);
		$this->assertSame('value * 2', $lambdas['__lambda_0']['body']);

		$evaluated = $el->evaluate($parsed, ['values' => [1, 5, 10]]);
		$compiled = $el->compile($parsed, ['values']);

		$this->assertSame([2, 10, 20], $evaluated);
		$this->assertSame('map(function ($value) { return ($value * 2); }, $values)', $compiled);
	}

	public function testLintWithArrowFunctionsSucceeds(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
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

		$this->expectNotToPerformAssertions();

		$el->lint('map((value) -> { value * 2 }, values)', ['values']);
	}

	public function testLintWithArrowFunctionsThrowsOnMainError(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();

		$this->expectException(SyntaxError::class);

		$el->lint('map((value) -> { value * 2 }, values', ['values']);
	}

	public function testLintWithArrowFunctionsThrowsOnLambdaBodyError(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();

		$this->expectException(SyntaxError::class);

		$el->lint('map((value) -> { value * }, values)', ['values']);
	}

	public function testLintWithArrowFunctionsThrowsOnUndefinedVariable(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();

		$this->expectException(SyntaxError::class);

		$el->lint('map((value) -> { value * undefined_var }, values)', ['values']);
	}

	public function testSubstrCollisionPrevention(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
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

		// Expression with 13 sequential arrow functions wrapped in run()
		$expression = 'run(() -> { 1 }) + run(() -> { 2 }) + run(() -> { 3 }) + run(() -> { 4 }) + run(() -> { 5 }) + run(() -> { 6 }) + run(() -> { 7 }) + run(() -> { 8 }) + run(() -> { 9 }) + run(() -> { 10 }) + run(() -> { 11 }) + run(() -> { 12 }) + run(() -> { 13 })';

		$compiled = $el->compile($expression);
		$evaluated = $el->evaluate($expression);

		$expectedCompiled = '((((((((((((run(function () { return 1; }) + run(function () { return 2; })) + run(function () { return 3; })) + run(function () { return 4; })) + run(function () { return 5; })) + run(function () { return 6; })) + run(function () { return 7; })) + run(function () { return 8; })) + run(function () { return 9; })) + run(function () { return 10; })) + run(function () { return 11; })) + run(function () { return 12; })) + run(function () { return 13; }))';

		$this->assertSame($expectedCompiled, $compiled);
		$this->assertSame(91, $evaluated);
	}

	public function testDeterministicCollisionAvoidanceWithUserVariables(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
		$el->addFunction(
			new ExpressionFunction(
				'apply',
				static fn(string ...$expressions) => sprintf('apply(%s)', implode(', ', $expressions)),
				static fn($args, SafeCallable $callback, $val) => $callback->getCallback()($val)
			)
		);

		// User defines a variable named "__lambda_0"
		$names = ['__lambda_0', 'val'];
		$values = ['__lambda_0' => 100, 'val' => 5];

		// The expression has an arrow function and references "__lambda_0"
		$expression = 'apply((x) -> { x + __lambda_0 }, val)';

		// Should compile using "__lambda_1" as the placeholder since "__lambda_0" is in $names
		$compiled = $el->compile($expression, $names);
		$evaluated = $el->evaluate($expression, $values);

		$this->assertSame('apply(function ($x) use ($__lambda_0) { return ($x + $__lambda_0); }, $val)', $compiled);
		$this->assertSame(105, $evaluated);
	}
}
