<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Clock;

/**
 * Waits between retries. Pass your own to RetryPolicy to wait some other
 * way, or not at all in tests.
 */
interface Sleeper
{
    public function sleep(float $seconds): void;
}
