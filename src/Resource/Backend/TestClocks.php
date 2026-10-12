<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Model\TestClock;

/**
 * A clock of its own for a subscription bought in test money through the
 * backend's test credential, to walk it through renewals without waiting.
 */
final readonly class TestClocks
{
    /** @internal obtain it from Client::backend()->testClocks() */
    public function __construct(private Caller $caller) {}

    /**
     * Gives the subscription its own clock, set to now.
     *
     * @return KeyedResult<TestClock>
     */
    #[Endpoint(Operation::AttachTestClock)]
    public function attach(string $subscription, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::AttachTestClock, TestClock::SCHEMA, TestClock::from(...), ['subscription' => $subscription], [], $idempotencyKey);
    }

    /**
     * Moves the subscription's clock on, running each renewal and retry that
     * falls inside the step at its own time.
     *
     * @param int<min, 31622400> $seconds the step, at most a year and a day
     * @return KeyedResult<TestClock>
     */
    #[Endpoint(Operation::AdvanceTestClock)]
    public function advance(string $subscription, int $seconds, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::AdvanceTestClock, TestClock::SCHEMA, TestClock::from(...), ['subscription' => $subscription], ['seconds' => $seconds], $idempotencyKey);
    }
}
