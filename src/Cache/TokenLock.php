<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Cache;

/**
 * A lock shared by every process that shares the cache, so that when the
 * backend's token is due, one of them asks for a new one and the rest take
 * it from the cache. Pass one to Config::$tokenLock built on your framework's
 * lock (a Redis or database lock, Laravel's Cache::lock(), Symfony Lock);
 * without one, each process that finds the token due asks for its own.
 */
interface TokenLock
{
    /**
     * Runs $fetch while holding the lock called $name, held for at most
     * $seconds so a process that dies cannot keep it; waits up to $seconds
     * for another holder to finish. When the lock cannot be had in that
     * time, run $fetch anyway: a second token request is harmless, a failed
     * call is not.
     *
     * @template T
     *
     * @param callable(): T $fetch
     * @return T
     */
    public function run(string $name, int $seconds, callable $fetch): mixed;
}
