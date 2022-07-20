<?php

namespace uuf6429\ExpressionLanguage;

use RuntimeException;

/**
 * A wrapper for an anonymous function.
 * We do not return anonymous functions directly for security reason, to avoid
 * calling arbitrary functions by returning arrays containing class/method or
 * string function names. From the userland, one can still get access to the
 * anonymous function using the various public methods.
 *
 * @author Christian Sciberras <christian@sciberras.me>
 */
class SafeCallable
{
    protected $callback;

    /**
     * Constructor.
     *
     * @param callable $callback The target callback
     */
    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    /**
     * @return callable
     */
    public function getCallback(): callable
    {
        return $this->callback;
    }

    /**
     * Call the callback with the provided arguments and returns result.
     *
     * @param mixed ...$arguments
     *
     * @return mixed
     */
    public function call(...$arguments)
    {
        return $this->callArray(...$arguments);
    }

    /**
     * Call the callback with the provided arguments and returns result.
     *
     * @param array $arguments
     *
     * @return mixed
     */
    public function callArray(array $arguments)
    {
        $callback = $this->getCallback();

        return $callback(...array_values($arguments));
    }

    public function __invoke()
    {
        throw new RuntimeException('Callback wrapper cannot be invoked, use $wrapper->getCallback() instead.');
    }
}
