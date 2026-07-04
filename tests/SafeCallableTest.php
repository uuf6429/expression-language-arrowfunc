<?php declare(strict_types=1);

namespace uuf6429\ExpressionLanguageTests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use uuf6429\ExpressionLanguage\SafeCallable;

/**
 * @internal
 */
final class SafeCallableTest extends TestCase
{
	public function testGetCallback(): void
	{
		$callable = static fn() => null;

		$safeCallable = new SafeCallable($callable);

		$this->assertSame($callable, $safeCallable->getCallback());
	}

	public function testInvokeMethod(): void
	{
		$safeCallable = new SafeCallable(static fn() => null);

		$this->expectExceptionObject(new RuntimeException('Callback cannot be invoked, use call() or getCallback() methods instead.'));

		$safeCallable->__invoke();
	}

	public function testInvokeMagic(): void
	{
		$safeCallable = new SafeCallable(static fn() => null);

		$this->expectExceptionObject(new RuntimeException('Callback cannot be invoked, use call() or getCallback() methods instead.'));

		$safeCallable();
	}

	public function testCallWithArgs(): void
	{
		$safeCallable = new SafeCallable(static fn(int $a, int $b) => $a + $b);

		$this->assertSame(5, $safeCallable->call(2, 3));
	}

	public function testCallWithoutArgs(): void
	{
		$safeCallable = new SafeCallable(static fn() => 123);

		$this->assertSame(123, $safeCallable->call());
	}
}
