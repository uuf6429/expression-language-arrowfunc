<?php declare(strict_types=1);

namespace uuf6429\ExpressionLanguageTests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ExpressionLanguage as SymfonyExpressionLanguage;
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
			new SymfonyExpressionLanguage\ExpressionFunction(
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
			new SymfonyExpressionLanguage\ExpressionFunction(
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
			new SymfonyExpressionLanguage\ExpressionFunction(
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
			new SymfonyExpressionLanguage\ExpressionFunction(
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
			new SymfonyExpressionLanguage\ExpressionFunction(
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

	/**
	 * @testWith ["map((value) -> { value * 2 }, values)", 4, 28, 17, 26]
	 *           ["map(value -> { value * 2 }, values)", 4, 26, 15, 24]
	 *           ["value -> { value * 2 }", 0, 22, 11, 20]
	 *           ["  value->{value * 2}  ", 2, 20, 10, 19]
	 *           ["( value->{value * 2} )", 2, 20, 10, 19]
	 */
	public function testParseWithArrowFunctions(string $expr, int $fromChar, int $untilChar, int $bodyFromChar, int $bodyUntilChar): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
		$el->addFunction(
			new SymfonyExpressionLanguage\ExpressionFunction(
				'map',
				static function (string ...$expressions) {
					return sprintf('map(%s)', implode(', ', $expressions));
				},
				static function ($args, SafeCallable $callback, array $array) {
					return array_map($callback->getCallback(), $array);
				}
			)
		);

		$parsed = $el->parse($expr, ['values']);
		$this->assertSame($expr, (string)$parsed);
		$this->assertSame(
			[
				'__lambda_0' => [
					'params' => ['value'],
					'body' => 'value * 2',
					'fromChar' => $fromChar,
					'untilChar' => $untilChar,
					'bodyFromChar' => $bodyFromChar,
					'bodyUntilChar' => $bodyUntilChar,
				],
			],
			$parsed->getLambdas()
		);

		$evaluated = $el->evaluate($parsed, ['values' => [1, 5, 10]]);
		$compiled = $el->compile($parsed, ['values']);
		if ($evaluated instanceof SafeCallable) {
			$this->assertSame(10, $evaluated->getCallback()(5));
			$this->assertSame('function ($value) { return ($value * 2); }', $compiled);
		} else {
			$this->assertSame([2, 10, 20], $evaluated);
			$this->assertSame('map(function ($value) { return ($value * 2); }, $values)', $compiled);
		}
	}

	public function testLintWithArrowFunctionsSucceeds(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
		$el->addFunction(
			new SymfonyExpressionLanguage\ExpressionFunction(
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

		$this->expectException(SymfonyExpressionLanguage\SyntaxError::class);

		$el->lint('map((value) -> { value * 2 }, values', ['values']);
	}

	public function testLintWithArrowFunctionsThrowsOnLambdaBodyError(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();

		$this->expectException(SymfonyExpressionLanguage\SyntaxError::class);

		$el->lint('map((value) -> { value * }, values)', ['values']);
	}

	public function testLintWithArrowFunctionsThrowsOnUndefinedVariable(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();

		$this->expectException(SymfonyExpressionLanguage\SyntaxError::class);

		$el->lint('map((value) -> { value * undefined_var }, values)', ['values']);
	}

	public function testSubstrCollisionPrevention(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
		$el->addFunction(
			new SymfonyExpressionLanguage\ExpressionFunction(
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
			new SymfonyExpressionLanguage\ExpressionFunction(
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

	public function testConstructorWithCustomBase(): void
	{
		$base = new SymfonyExpressionLanguage\ExpressionLanguage();
		$base->addFunction(
			new SymfonyExpressionLanguage\ExpressionFunction(
				'custom_func',
				static fn() => 'custom_func()',
				static fn() => 'custom_result'
			)
		);

		$el = new ExpressionLanguageWithArrowFunctions($base);
		$this->assertSame('custom_result', $el->evaluate('custom_func()'));
	}

	public function testRegister(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
		$el->register(
			'register_func',
			static fn() => 'register_func()',
			static fn() => 'register_result'
		);

		$this->assertSame('register_result', $el->evaluate('register_func()'));
		$this->assertSame('register_func()', $el->compile('register_func()'));
	}

	public function testRegisterProvider(): void
	{
		$provider = new class implements SymfonyExpressionLanguage\ExpressionFunctionProviderInterface {
			public function getFunctions(): array
			{
				return [
					new SymfonyExpressionLanguage\ExpressionFunction(
						'provider_func',
						static fn() => 'provider_func()',
						static fn() => 'provider_result'
					),
				];
			}
		};

		$el = new ExpressionLanguageWithArrowFunctions();
		$el->registerProvider($provider);

		$this->assertSame('provider_result', $el->evaluate('provider_func()'));
	}

	public function testStandardExpressionObject(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
		$expr = new SymfonyExpressionLanguage\Expression('1 + 2');

		$this->assertSame('(1 + 2)', $el->compile($expr));
		$this->assertSame(3, $el->evaluate($expr));

		$parsed = $el->parse($expr, []);
		$this->assertSame('1 + 2', (string)$parsed);

		$el->lint($expr, []);
	}

	public function testBaseSymfonyParsedExpressionObject(): void
	{
		$baseEl = new SymfonyExpressionLanguage\ExpressionLanguage();
		$symfonyParsed = $baseEl->parse('1 + 2', []);

		$el = new ExpressionLanguageWithArrowFunctions();

		$this->assertSame('(1 + 2)', $el->compile($symfonyParsed));
		$this->assertSame(3, $el->evaluate($symfonyParsed));

		$parsed = $el->parse($symfonyParsed, []);
		$this->assertSame('1 + 2', (string)$parsed);

		$el->lint($symfonyParsed, []);
	}

	public function testParseWithAlreadyParsedExpression(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
		$parsed1 = $el->parse('1 + 2', []);
		$parsed2 = $el->parse($parsed1, []);

		$this->assertSame($parsed1, $parsed2);
	}

	public function testParameterListsWithFormattingVariations(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
		$el->addFunction(
			new SymfonyExpressionLanguage\ExpressionFunction(
				'calc',
				static fn(string ...$expressions) => sprintf('calc(%s)', implode(', ', $expressions)),
				static fn($args, SafeCallable $callback, $a, $b) => $callback->getCallback()($a, $b)
			)
		);

		$expression1 = 'calc((x, y, ) -> { x * y }, multiplier, base)';
		$this->assertSame('calc(function ($x, $y) { return ($x * $y); }, $multiplier, $base)', $el->compile($expression1, ['multiplier', 'base']));
		$this->assertSame(12, $el->evaluate($expression1, ['multiplier' => 3, 'base' => 4]));

		$expression2 = 'calc(( x , , y ) -> { x * y }, multiplier, base)';
		$this->assertSame('calc(function ($x, $y) { return ($x * $y); }, $multiplier, $base)', $el->compile($expression2, ['multiplier', 'base']));
		$this->assertSame(12, $el->evaluate($expression2, ['multiplier' => 3, 'base' => 4]));
	}

	public function testSuperglobalsExclusion(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
		$el->addFunction(
			new SymfonyExpressionLanguage\ExpressionFunction(
				'run',
				static fn(string ...$expressions) => sprintf('run(%s)', implode(', ', $expressions)),
				static fn($args, SafeCallable $callback) => $callback->getCallback()()
			)
		);

		$expression = 'run((x) -> { x + _GET })';
		$compiled = $el->compile($expression, ['_GET']);

		$this->assertSame('run(function ($x) { return ($x + $_GET); })', $compiled);
	}

	public function testStringLiteralLexerSkippingWithEscapedQuotes(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();

		$exprSingle = '"some text \\\' (a) -> { a } \\\'"';
		$compiledSingle = $el->compile($exprSingle);
		$evaluatedSingle = $el->evaluate($exprSingle);
		$this->assertSame('"some text \' (a) -> { a } \'"', $compiledSingle);
		$this->assertSame("some text ' (a) -> { a } '", $evaluatedSingle);

		$exprDouble = '\'some text \\" (a) -> { a } \\"\'';
		$compiledDouble = $el->compile($exprDouble);
		$evaluatedDouble = $el->evaluate($exprDouble);
		$this->assertSame('"some text \\" (a) -> { a } \\""', $compiledDouble);
		$this->assertSame('some text " (a) -> { a } "', $evaluatedDouble);
	}

	public function testDeepScopingOfNestedArrowFunctions(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
		$el->addFunction(
			new SymfonyExpressionLanguage\ExpressionFunction(
				'map',
				static fn(string ...$expressions) => sprintf('map(%s)', implode(', ', $expressions)),
				static fn($args, SafeCallable $callback, array $array) => array_map($callback->getCallback(), $array)
			)
		);

		$expression = 'map((value) -> { map((v) -> { v * value + factor }, values) }, values)';
		$compiled = $el->compile($expression, ['values', 'factor']);
		$evaluated = $el->evaluate($expression, ['values' => [2, 3], 'factor' => 10]);

		$this->assertSame(
			'map(function ($value) use ($values) { return map(function ($v) use ($value, $factor) { return (($v * $value) + $factor); }, $values); }, $values)',
			$compiled
		);
		$this->assertSame([[14, 16], [16, 19]], $evaluated);
	}

	/**
	 * @dataProvider provideMalformedExpressions
	 * @param list<string> $names
	 */
	public function testMalformedArrowFunctionsAndEdgeCases(string $expression, array $names = []): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();

		// Preprocessor should fail to match them as valid arrow functions,
		// falling back to Symfony's standard parser which will reject "->",
		// or throwing a SyntaxError.
		$this->expectException(SymfonyExpressionLanguage\SyntaxError::class);

		$el->compile($expression, $names);
	}

	/**
	 * @return iterable<string, array{expression: string, names?: list<string>}>
	 */
	public static function provideMalformedExpressions(): iterable
	{
		yield 'no body block after arrow' => [
			'expression' => '(x) -> x',
		];

		yield 'malformed parenthesis backwards scan' => [
			'expression' => ')x) -> { x }',
		];

		yield 'malformed curly braces forwards scan' => [
			'expression' => '(x) -> { x',
		];

		yield 'no parenthesis and no body block' => [
			'expression' => 'x -> y',
		];

		yield 'invalid bare parameter' => [
			'expression' => '1x -> { x }',
		];

		yield 'bare parameter after invalid delimiter' => [
			'expression' => '1 + x -> { x }',
		];

		yield 'empty parenthesis backwards scan' => [
			'expression' => '-> { x }',
		];
	}

	public function testUnusualWhitespaceAndFormatting(): void
	{
		$el = new ExpressionLanguageWithArrowFunctions();
		$el->addFunction(
			new SymfonyExpressionLanguage\ExpressionFunction(
				'run',
				static fn(string ...$expressions) => sprintf('run(%s)', implode(', ', $expressions)),
				static fn($args, SafeCallable $callback) => $callback->getCallback()(10, 20)
			)
		);

		// Arrow function with carriage returns, tabs, and newlines in params and body
		$expression = "run((\r\n\t x,\r\n\t y\r\n) -> {\r\n\t x + y\r\n})";
		$compiled = $el->compile($expression);
		$evaluated = $el->evaluate($expression);

		$this->assertSame('run(function ($x, $y) { return ($x + $y); })', $compiled);
		$this->assertSame(30, $evaluated);
	}
}
