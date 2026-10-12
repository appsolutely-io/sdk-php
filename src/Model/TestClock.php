<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A test subscription's own clock, and the subscription as it stands at that time.
 */
final readonly class TestClock
{
    /** @internal */
    public const string SCHEMA = 'TestClock';

    /**
     * @param string $id the subscription's id
     * @param DateTimeImmutable $clockNow the time on the subscription's clock
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public string $status,
        public ?DateTimeImmutable $currentPeriodEnd,
        public DateTimeImmutable $clockNow,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            status: $json->string('status'),
            currentPeriodEnd: $json->nullableTime('current_period_end'),
            clockNow: $json->time('clock_now'),
            extra: $json->extra(),
        );
    }
}
