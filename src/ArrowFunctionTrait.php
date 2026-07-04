<?php

namespace uuf6429\ExpressionLanguage;

use Symfony\Component\ExpressionLanguage\Expression;

trait ArrowFunctionTrait
{
	/**
	 * Evaluates an expression with custom arrow function syntax support.
	 *
	 * @param Expression|string $expression
	 * @param array<string, mixed> $values
	 * @return mixed
	 */
	private function evaluateWithArrowFunctions($expression, array $values = [])
	{
		if (!is_string($expression)) {
			return parent::evaluate($expression, $values);
		}

		$res = $this->preprocess($expression);
		$preprocessedExpr = $res['expression'];
		$lambdas = $res['lambdas'];

		if (count($lambdas) > 0) {
			// Helper closure to evaluate a lambda given its name, arguments, and dynamic scope
			$evaluateLambda = function (string $lambdaName, array $args, array $currentScope) use ($lambdas, &$evaluateLambda) {
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

				return parent::evaluate($lambda['body'], $mergedScope);
			};

			// Inject the lambdas as SafeCallables into the variable values context
			foreach ($lambdas as $lambdaName => $lambda) {
				$values[$lambdaName] = new SafeCallable(function (...$args) use ($lambdaName, &$values, $evaluateLambda) {
					return $evaluateLambda($lambdaName, $args, $values);
				});
			}
		}

		return parent::evaluate($preprocessedExpr, $values);
	}

	/**
	 * Preprocesses the expression to extract arrow functions and replace them with standard placeholder variables.
	 * Handles nested arrow functions by processing them innermost-to-outermost.
	 *
	 * @return array{expression: string, lambdas: array<string, array{params: array<string>, body: string}>}
	 */
	private function preprocess(string $expression): array
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
	 * Locates the positions of all valid "->" operators, skipping string literals.
	 *
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

	/**
	 * Compiles an expression with custom arrow function syntax support.
	 *
	 * @param Expression|string $expression
	 * @param array<array-key, string> $names
	 */
	private function compileWithArrowFunctions($expression, array $names = []): string
	{
		if (!is_string($expression)) {
			return parent::compile($expression, $names);
		}

		$res = $this->preprocess($expression);
		$preprocessedExpr = $res['expression'];
		$lambdas = $res['lambdas'];

		// Inject placeholders as expected variable names into standard Symfony compile
		$lambdaNames = array_keys($lambdas);
		$compiled = parent::compile($preprocessedExpr, array_merge($names, $lambdaNames));

		// Get all lambda parameters in the entire expression
		$allLambdaParams = array_merge([], ...array_column($lambdas, 'params'));

		// Compile each lambda body and format them into valid PHP closures
		$compiledLambdas = [];
		foreach ($lambdas as $lambdaName => $lambda) {
			// Include all known names, lambda names, and all lambda parameters
			$compiledBody = parent::compile($lambda['body'], array_merge($names, $lambdaNames, $allLambdaParams));

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
}
