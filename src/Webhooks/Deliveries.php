<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks;

use Appsolutely\Sdk\Exception\InvalidArgumentValueException;
use Psr\SimpleCache\CacheInterface;

/**
 * Remembers which delivery ids the integrator has handled, so a retry or a
 * redelivery of one is acknowledged without running the handler twice.
 *
 * Mark an id only after the handler has succeeded: a delivery whose handler
 * failed must run again on the next attempt.
 *
 *     if (!$deliveries->wasHandled($event->id)) {
 *         handle($event);
 *         $deliveries->markHandled($event->id);
 *     }
 *     // answer 2xx in both cases
 *
 * The check and the mark are two cache calls, so two concurrent attempts of
 * one id can both run the handler; a handler with external effects should
 * still be idempotent on the id.
 *
 * Give each endpoint its own namespace when several share one cache (two
 * apps, or two endpoints of one app, subscribed to the same events): one
 * delivery reaches each of them under the same id, and without a namespace
 * the second would be skipped as already handled.
 */
final readonly class Deliveries
{
    /**
     * Three days: comfortably beyond the sender's retry schedule, which spans
     * about 28 hours, and a manual redelivery soon after.
     */
    public const int DEFAULT_TTL = 259200;

    private const string KEY_PREFIX = 'appsolutely.webhooks.handled.';

    /**
     * @param string $namespace one per endpoint sharing the cache; the default, '', keeps the keys
     *                          of ids handled before namespaces existed
     */
    public function __construct(
        private CacheInterface $store,
        private int $ttl = self::DEFAULT_TTL,
        private string $namespace = '',
    ) {
        if ($ttl <= 0) {
            throw new InvalidArgumentValueException('The retention of handled delivery ids must be a positive number of seconds.');
        }
    }

    public function wasHandled(string $id): bool
    {
        return $this->store->get($this->key($id)) !== null;
    }

    public function markHandled(string $id): void
    {
        $this->store->set($this->key($id), true, $this->ttl);
    }

    /**
     * Hashed so any id is a valid PSR-16 key whatever characters it holds.
     * A namespaced key has its own prefix, so it can never equal a default
     * one, and the namespace's length goes into the hash, so namespace "a"
     * with id "bc" is not namespace "ab" with id "c".
     */
    private function key(string $id): string
    {
        return $this->namespace === ''
            ? self::KEY_PREFIX . hash('sha256', $id)
            : self::KEY_PREFIX . 'ns.' . hash('sha256', strlen($this->namespace) . ':' . $this->namespace . $id);
    }
}
