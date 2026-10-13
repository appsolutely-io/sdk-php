<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Tests\Support;

use Appsolutely\Sdk\Cache\TokenLock;

/**
 * A lock that records each use and, the first time, lets "another process"
 * run while it is held; when $held, another process holds it throughout.
 */
final class RecordingLock implements TokenLock
{
    /** @var list<array{name: string, seconds: int}> */
    public array $runs = [];

    /** @var list<array{name: string, seconds: int}> */
    public array $tries = [];

    /**
     * @param (\Closure(): void)|null $meanwhile
     */
    public function __construct(private ?\Closure $meanwhile = null, public bool $held = false) {}

    public function runIfFree(string $name, int $seconds, callable $fetch): ?string
    {
        $this->tries[] = ['name' => $name, 'seconds' => $seconds];

        return $this->held ? null : $fetch();
    }

    public function run(string $name, int $seconds, callable $fetch): mixed
    {
        $this->runs[] = ['name' => $name, 'seconds' => $seconds];
        $meanwhile = $this->meanwhile;
        $this->meanwhile = null;
        if ($meanwhile !== null) {
            $meanwhile();
        }

        return $fetch();
    }
}
