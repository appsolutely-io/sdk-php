<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Cache;

use Appsolutely\Sdk\Clock\SystemClock;
use Appsolutely\Sdk\Exception\InvalidCacheKeyException;
use DateInterval;
use Psr\Clock\ClockInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * A PSR-16 cache that lives as long as the PHP process.
 *
 * The client falls back to it when no cache is configured, so discovery and
 * keys are at least not fetched twice within one request. Production code
 * should pass the application's shared cache instead: under PHP-FPM every
 * request is a new process and this cache starts empty each time.
 */
final class InMemoryCache implements CacheInterface
{
    private const string RESERVED = '{}()/\\@:';

    /** @var array<string, array{value: mixed, expiresAt: int|null}> */
    private array $items = [];

    public function __construct(private readonly ClockInterface $clock = new SystemClock()) {}

    public function get(string $key, mixed $default = null): mixed
    {
        self::assertKey($key);

        if (!$this->has($key)) {
            return $default;
        }

        return $this->items[$key]['value'];
    }

    public function set(string $key, #[\SensitiveParameter] mixed $value, int|DateInterval|null $ttl = null): bool
    {
        self::assertKey($key);

        $seconds = $this->seconds($ttl);
        if ($seconds !== null && $seconds <= 0) {
            unset($this->items[$key]);

            return true;
        }

        $this->items[$key] = [
            'value' => $value,
            'expiresAt' => $seconds === null ? null : $this->now() + $seconds,
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        self::assertKey($key);
        unset($this->items[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    /**
     * @param iterable<mixed> $keys
     * @return array<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): array
    {
        $values = [];
        foreach ($keys as $key) {
            $key = self::stringKey($key);
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * @param iterable<mixed, mixed> $values
     */
    public function setMultiple(#[\SensitiveParameter] iterable $values, int|DateInterval|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set(self::stringKey($key), $value, $ttl);
        }

        return true;
    }

    /**
     * @param iterable<mixed> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete(self::stringKey($key));
        }

        return true;
    }

    public function has(string $key): bool
    {
        self::assertKey($key);

        if (!isset($this->items[$key])) {
            return false;
        }

        $expiresAt = $this->items[$key]['expiresAt'];
        if ($expiresAt !== null && $expiresAt <= $this->now()) {
            unset($this->items[$key]);

            return false;
        }

        return true;
    }

    private function seconds(int|DateInterval|null $ttl): ?int
    {
        if ($ttl instanceof DateInterval) {
            $now = $this->clock->now();

            return $now->add($ttl)->getTimestamp() - $now->getTimestamp();
        }

        return $ttl;
    }

    private function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }

    private static function stringKey(mixed $key): string
    {
        if (is_int($key)) {
            return (string) $key;
        }

        if (!is_string($key)) {
            throw new InvalidCacheKeyException(sprintf('A cache key must be a string, got %s.', get_debug_type($key)));
        }

        return $key;
    }

    private static function assertKey(string $key): void
    {
        if ($key === '' || strpbrk($key, self::RESERVED) !== false) {
            throw new InvalidCacheKeyException(sprintf('"%s" is not a valid PSR-16 cache key.', $key));
        }
    }
}
