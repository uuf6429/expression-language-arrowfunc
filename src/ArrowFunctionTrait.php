<?php declare(strict_types=1);

namespace uuf6429\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ParsedExpression as SymfonyParsedExpression;

trait ArrowFunctionTrait
{
	/**
	 * @param Expression|string $expression
	 * @param array<array-key, string> $names
	 */
	abstract protected function compileWithoutArrowFunctions($expression, array $names = []): string;

	/**
	 * @param Expression|string $expression
	 * @param array<string, mixed> $values
	 * @return mixed
	 */
	abstract protected function evaluateWithoutArrowFunctions($expression, array $values = []);

	/**
	 * @param Expression|string $expression
	 * @param array<array-key, string> $names
	 */
	abstract protected function parseWithoutArrowFunctions($expression, array $names): SymfonyParsedExpression;

	/**
	 * @param Expression|string $expression
	 * @param null|array<array-key, string> $names
	 */
	abstract protected function lintWithoutArrowFunctions($expression, ?array $names): void;

	/**
	 * Compiles an expression with custom arrow function syntax support.
	 *
	 * @param Expression|string $expression
	 * @param array<array-key, string> $names
	 * @api
	 */
	private function compileWithArrowFunctions($expression, array $names = []): string
	{
		if ($expression instanceof ParsedExpression) {
			$preprocessedExpr = $expression;
			$lambdas = $expression->getLambdas();
		} elseif ($expression instanceof SymfonyParsedExpression) {
			$preprocessedExpr = $expression;
			$lambdas = [];
		} elseif (is_string($expression)) {
			$res = $this->preprocessArrowFunctions($expression);
			$preprocessedExpr = $res['expression'];
			$lambdas = $res['lambdas'];
		} else {
			return $this->compileWithoutArrowFunctions($expression, $names);
		}

		// Inject placeholders as expected variable names into standard Symfony compile
		$lambdaNames = array_keys($lambdas);
		$compiled = $this->compileWithoutArrowFunctions($preprocessedExpr, array_merge($names, $lambdaNames));

		// Get all lambda parameters in the entire expression
		$allLambdaParams = array_merge([], ...array_column($lambdas, 'params'));

		// Compile each lambda body and format them into valid PHP closures
		$compiledLambdas = [];
		foreach ($lambdas as $lambdaName => $lambda) {
			// Include all known names, lambda names, and all lambda parameters
			$compiledBody = $this->compileWithoutArrowFunctions($lambda['body'], array_merge($names, $lambdaNames, $allLambdaParams));

			// Extract all PHP variable names from the compiled body
			preg_match_all('/\$([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)/', $compiledBody, $matches); // TODO isn't there a risk that we replace matching keywords that aren't really code? e.g. '$_lambda_123'
			$allVars = array_unique($matches[1]);

			// Exclude lambda's own parameters, lambda placeholders, and superglobals
			$useVarNames = array_filter($allVars, static function ($var) use ($lambda) {
				if (in_array($var, $lambda['params'], true)) {
					return false;
				}
				if (strpos($var, '__lambda_') === 0) {
					return false;
				}
				if (in_array($var, ['GLOBALS', '_SERVER', '_REQUEST', '_POST', '_GET', '_FILES', '_ENV', '_COOKIE', '_SESSION'], true)) {
					return false;
				}
				return true;
			});

			$useClause = '';
			if (count($useVarNames) > 0) {
				$useClause = sprintf(
					' use (%s)',
					implode(', ', array_map(static fn($varName) => "\${$varName}", $useVarNames))
				);
			}

			$compiledParams = implode(', ', array_map(static fn($paramName) => "\${$paramName}", $lambda['params']));

			$compiledLambdas[$lambdaName] = sprintf('function (%s)%s { return %s; }', $compiledParams, $useClause, $compiledBody);
		}

		// Replace all $__lambda_X variables in both the main compiled string and in other lambda closures
		foreach ($compiledLambdas as $lambdaName => &$lambdaCode) {
			$compiled = str_replace("\${$lambdaName}", $lambdaCode, $compiled);
			foreach ($compiledLambdas as &$otherCode) {
				$otherCode = str_replace("\${$lambdaName}", $lambdaCode, $otherCode);
			}
		}
		unset($lambdaCode, $otherCode);

		return $compiled;
	}

	/**
	 * Evaluates an expression with custom arrow function syntax support.
	 *
	 * @param Expression|string $expression
	 * @param array<string, mixed> $values
	 * @return mixed
	 * @api
	 */
	private function evaluateWithArrowFunctions($expression, array $values = [])
	{
		if ($expression instanceof ParsedExpression) {
			$preprocessedExpr = $expression;
			$lambdas = $expression->getLambdas();
		} elseif ($expression instanceof SymfonyParsedExpression) {
			$preprocessedExpr = $expression;
			$lambdas = [];
		} elseif (is_string($expression)) {
			$res = $this->preprocessArrowFunctions($expression);
			$preprocessedExpr = $res['expression'];
			$lambdas = $res['lambdas'];
		} else {
			return $this->evaluateWithoutArrowFunctions($expression, $values);
		}

		if (count($lambdas) > 0) {
			// Helper closure to evaluate a lambda given its name, arguments, and dynamic scope
			$evaluateLambda = function (string $lambdaName, array $args, array $currentScope) use ($lambdas, &$evaluateLambda) {
				/**
				 * @var array<string, mixed> $args
				 * @var array<string, mixed> $currentScope
				 */
				$lambda = $lambdas[$lambdaName];

				$passedArgs = [];
				foreach ($lambda['params'] as $idx => $paramName) {
					$passedArgs[$paramName] = $args[$idx] ?? null;
				}
				$mergedScope = array_merge($currentScope, $passedArgs);

				// Lexical Scoping: rebuild SafeCallables in the scope to capture the new dynamic variables
				foreach ($lambdas as $otherName => $_) {
					$mergedScope[$otherName] = new SafeCallable(function (...$otherArgs) use ($otherName, &$mergedScope, $evaluateLambda) {
						return $evaluateLambda($otherName, $otherArgs, $mergedScope);
					});
				}

				return $this->evaluateWithoutArrowFunctions($lambda['body'], $mergedScope);
			};

			// Inject the lambdas as SafeCallables into the variable values context
			foreach ($lambdas as $lambdaName => $lambda) {
				$values[$lambdaName] = new SafeCallable(function (...$args) use ($lambdaName, &$values, $evaluateLambda) {
					return $evaluateLambda($lambdaName, $args, $values);
				});
			}
		}

		return $this->evaluateWithoutArrowFunctions($preprocessedExpr, $values);
	}

	/**
	 * Parses an expression with custom arrow function syntax support.
	 *
	 * @param Expression|string $expression
	 * @param array<array-key, string> $names
	 * @api
	 */
	private function parseWithArrowFunctions($expression, array $names): ParsedExpression
	{
		if ($expression instanceof ParsedExpression) {
			return $expression;
		}

		if ($expression instanceof SymfonyParsedExpression) {
			return new ParsedExpression((string)$expression, $expression->getNodes(), []);
		}

		if (!is_string($expression)) {
			$baseParsed = $this->parseWithoutArrowFunctions($expression, $names);
			return new ParsedExpression((string)$baseParsed, $baseParsed->getNodes(), []);
		}

		$res = $this->preprocessArrowFunctions($expression);
		$preprocessedExpr = $res['expression'];
		$lambdas = $res['lambdas'];

		$lambdaNames = array_keys($lambdas);
		$mergedNames = array_merge($names, $lambdaNames);

		$baseParsed = $this->parseWithoutArrowFunctions($preprocessedExpr, $mergedNames);

		return new ParsedExpression($expression, $baseParsed->getNodes(), $lambdas);
	}

	/**
	 * Lints an expression with custom arrow function syntax support.
	 *
	 * @param Expression|string $expression
	 * @param null|array<array-key, string> $names
	 * @api
	 */
	private function lintWithArrowFunctions($expression, ?array $names): void
	{
		if ($expression instanceof SymfonyParsedExpression) {
			return;
		}

		if (!is_string($expression)) {
			$this->lintWithoutArrowFunctions($expression, $names);
			return;
		}

		$res = $this->preprocessArrowFunctions($expression);
		$preprocessedExpr = $res['expression'];
		$lambdas = $res['lambdas'];

		$lambdaNames = array_keys($lambdas);
		$mergedNames = $names === null ? null : array_merge($names, $lambdaNames);

		$this->lintWithoutArrowFunctions($preprocessedExpr, $mergedNames);

		$allLambdaParams = array_merge([], ...array_column($lambdas, 'params'));
		foreach ($lambdas as $lambda) {
			$lambdaMergedNames = $names === null ? null : array_merge($names, $lambdaNames, $allLambdaParams);
			$this->lintWithoutArrowFunctions($lambda['body'], $lambdaMergedNames);
		}
	}

	/**
	 * @return array{expression: string, lambdas: array<string, array{params: array<string>, body: string}>}
	 */
	private function preprocessArrowFunctions(string $expression): array
	{
		$lambdas = [];
		$lambdaCount = 0;

		while (true) {
			$positions = $this->getArrowPositions($expression);
			if (count($positions) === 0) {
				break;
			}

			$selectedStart = null;
			$selectedEnd = null;
			$selectedParams = null;
			$selectedBody = null;

			foreach ($positions as $pos) {
				// 1. Scan backwards to extract the parameter list: (param1, param2)
				$i = $pos - 1;
				while ($i >= 0 && in_array($expression[$i], [" ", "\t", "\r", "\n", "\v", "\f"], true)) {
					$i--;
				}
				if ($i < 0 || $expression[$i] !== ')') {
					continue;
				}
				$endParamPos = $i;
				$depth = 1;
				$i--;
				while ($i >= 0 && $depth > 0) {
					if ($expression[$i] === ')') {
						$depth++;
					} elseif ($expression[$i] === '(') {
						$depth--;
					}
					if ($depth > 0) {
						$i--;
					}
				}
				if ($depth > 0) {
					continue; // Malformed parenthesis
				}
				$startParamPos = $i;

				// 2. Scan forwards to extract the body block: { body_expr }
				$i = $pos + 2;
				$len = strlen($expression);
				while ($i < $len && in_array($expression[$i], [" ", "\t", "\r", "\n", "\v", "\f"], true)) {
					$i++;
				}
				if ($i >= $len || $expression[$i] !== '{') {
					continue;
				}
				$startBodyPos = $i;
				$depth = 1;
				$i++;
				while ($i < $len && $depth > 0) {
					if ($expression[$i] === '{') {
						$depth++;
					} elseif ($expression[$i] === '}') {
						$depth--;
					}
					if ($depth > 0) {
						$i++;
					}
				}
				if ($depth > 0) {
					continue; // Malformed curly braces
				}
				$endBodyPos = $i;

				$bodyStr = substr($expression, $startBodyPos + 1, $endBodyPos - $startBodyPos - 1);

				// If this body contains "->", skip it (resolve the innermost arrow first)
				if (count($this->getArrowPositions($bodyStr)) > 0) {
					continue;
				}

				$selectedParams = substr($expression, $startParamPos + 1, $endParamPos - $startParamPos - 1);
				$selectedBody = $bodyStr;
				$selectedStart = $startParamPos;
				$selectedEnd = $endBodyPos;
				break;
			}

			if ($selectedStart === null) {
				break; // No further matchable/valid arrow functions
			}

			// Parse individual parameter names
			$paramNames = array_filter(
				array_map('trim', explode(',', (string)$selectedParams)),
				static fn(string $param) => $param !== ''
			);

			$lambdaName = "__lambda_{$lambdaCount}";
			$lambdas[$lambdaName] = [
				'params' => $paramNames,
				'body' => trim((string)$selectedBody),
			];
			$lambdaCount++;

			// Replace arrow function with the placeholder variable
			$expression = substr($expression, 0, $selectedStart) . $lambdaName . substr($expression, (int)$selectedEnd + 1);
		}

		return [
			'expression' => $expression,
			'lambdas' => $lambdas,
		];
	}

	/**
	 * @return list<int>
	 */
	private function getArrowPositions(string $expression): array
	{
		$positions = [];
		$i = 0;
		$len = strlen($expression);
		while ($i < $len) {
			// Skip string literals to avoid matching inside strings like 'a -> b'
			if ($expression[$i] === "'" || $expression[$i] === '"') {
				$quote = $expression[$i];
				$i++;
				while ($i < $len) {
					if ($expression[$i] === $quote) {
						$i++;
						break;
					}
					if ($expression[$i] === '\\') {
						$i += 2;
					} else {
						$i++;
					}
				}
				continue;
			}

			if ($expression[$i] === '-' && $i + 1 < $len && $expression[$i + 1] === '>') {
				$positions[] = $i;
				$i += 2;
				continue;
			}
			$i++;
		}
		return $positions;
	}
}
