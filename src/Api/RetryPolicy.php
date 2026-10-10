<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Api;

use Appsolutely\Sdk\Clock\Sleeper;
use Appsolutely\Sdk\Clock\SystemSleeper;
use Appsolutely\Sdk\Exception\InvalidArgumentValueException;

/**
 * How Site API calls that may safely be repeated are retried: reads, and
 * writes sent with an Idempotency-Key, which the site answers with the first
 * answer instead of running again. They are retried after a network
 * failure, a 429, a 503, and (for a keyed write) the 409 of a request with
 * the same key still running. A write without a key is never retried, nor
 * is any other refusal.
 *
 * Each wait is what the site asks in Retry-After (or, on a 429 without it,
 * until the spent RateLimit quota turns over); failing that, a backoff that
 * doubles from $baseDelay, jittered between half and all of it so that
 * clients refused together do not return together. A wait the site asks
 * for beyond $maxDelay is not made: the refusal is thrown instead.
 */
final readonly class RetryPolicy
{
    public const int MAX_RETRIES = 10;
    public const int MAX_DELAY = 300;

    public Sleeper $sleeper;

    /**
     * @param int $maxRetries retries after the first attempt, 0 to 10; 0 turns retrying off
     * @param float $baseDelay seconds of the first backoff, doubled on each further one
     * @param int $maxDelay the longest single wait in seconds, 1 to 300
     * @param Sleeper|null $sleeper how to wait; the process sleeps by default
     */
    public function __construct(
        public int $maxRetries = 2,
        public float $baseDelay = 0.5,
        public int $maxDelay = 30,
        ?Sleeper $sleeper = null,
    ) {
        if ($maxRetries < 0 || $maxRetries > self::MAX_RETRIES) {
            throw new InvalidArgumentValueException(sprintf('The retries must be between 0 and %d, got %d.', self::MAX_RETRIES, $maxRetries));
        }
        if ($maxDelay < 1 || $maxDelay > self::MAX_DELAY) {
            throw new InvalidArgumentValueException(sprintf('The longest wait must be between 1 and %d seconds, got %d.', self::MAX_DELAY, $maxDelay));
        }
        if ($baseDelay <= 0 || $baseDelay > $maxDelay) {
            throw new InvalidArgumentValueException(sprintf('The first backoff must be above zero and at most the longest wait, got %s seconds.', $baseDelay));
        }

        $this->sleeper = $sleeper ?? new SystemSleeper();
    }

    /** No retries: every failure is thrown at once. */
    public static function none(): self
    {
        return new self(maxRetries: 0);
    }

    /**
     * The jittered backoff before the given retry, counted from 1.
     */
    public function backoff(int $retry): float
    {
        $ceiling = min((float) $this->maxDelay, $this->baseDelay * 2 ** max(0, $retry - 1));

        return $ceiling / 2 + $ceiling / 2 * (random_int(0, 1_000_000) / 1_000_000);
    }
}
