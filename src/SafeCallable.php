<?php declare(strict_types=1);

namespace uuf6429\ExpressionLanguage;

use RuntimeException;

/**
 * A wrapper for an anonymous function.
 * We do not return anonymous functions directly for security reason, to avoid
 * calling arbitrary functions by returning arrays containing class/method or
 * string function names. From the userland, one can still get access to the
 * anonymous function using the various public methods.
 */
final class SafeCallable
{
	/**
	 * @var callable
	 */
	protected $callback;

	/**
	 * @param callable $callback The target callback to wrap
	 */
	public function __construct(callable $callback)
	{
		$this->callback = $callback;
	}

	/**
	 * Calls the wrapped callback with the provided arguments and returns result.
	 *
	 * @param array<array-key, mixed> $arguments
	 * @return mixed
	 */
	public function call(...$arguments)
	{
		return $this->getCallback()(...$arguments);
	}

	/**
	 * @return callable
	 */
	public function getCallback(): callable
	{
		return $this->callback;
	}

	public function __invoke(): void
	{
		throw new RuntimeException('Callback cannot be invoked, use call() or getCallback() methods instead.');
	}
}
