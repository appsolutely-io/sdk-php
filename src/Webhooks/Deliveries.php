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
 */
final readonly class Deliveries
{
    /**
     * Three days: comfortably beyond the sender's retry schedule, which spans
     * about 28 hours, and a manual redelivery soon after.
     */
    public const int DEFAULT_TTL = 259200;

    public function __construct(
        private CacheInterface $store,
        private int $ttl = self::DEFAULT_TTL,
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
     */
    private function key(string $id): string
    {
        return 'appsolutely.webhooks.handled.' . hash('sha256', $id);
    }
}
