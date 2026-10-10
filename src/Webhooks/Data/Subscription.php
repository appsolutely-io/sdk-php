<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

use DateTimeImmutable;

/**
 * A subscription as the site describes it.
 */
final readonly class Subscription
{
    /**
     * @param string $type the kind of record, `Subscription`
     * @param string $status `trialing`, `active`, `past_due`, `on_hold`, `incomplete` or `canceled`
     * @param string $mode `production` or `test`: the money the subscription runs on
     * @param bool $cancelAtPeriodEnd whether it stops when the current period ends
     * @param string|null $transitionCause what moved it to its current status
     * @param string|null $recoveryReason why a payment is being recovered
     * @param string|null $subjectReference what the subscription is for beyond the account it bills
     * @param array<string, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $status,
        public string $mode,
        public bool $cancelAtPeriodEnd,
        public ?string $transitionCause,
        public ?string $recoveryReason,
        public ?DateTimeImmutable $currentPeriodStart,
        public ?DateTimeImmutable $currentPeriodEnd,
        public ?DateTimeImmutable $trialEndsAt,
        public ?DateTimeImmutable $endedAt,
        public ?string $subjectReference,
        public ?PendingPeriod $pendingPeriod,
        public array $extra = [],
    ) {}

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        $pending = $fields->nullableObject('pending_period');

        return new self(
            $fields->string('id'),
            $fields->string('type'),
            $fields->string('status'),
            $fields->string('mode'),
            $fields->bool('cancel_at_period_end'),
            $fields->nullableString('transition_cause'),
            $fields->nullableString('recovery_reason'),
            $fields->nullableTime('current_period_start'),
            $fields->nullableTime('current_period_end'),
            $fields->nullableTime('trial_ends_at'),
            $fields->nullableTime('ended_at'),
            $fields->nullableString('subject_reference'),
            $pending === null ? null : PendingPeriod::read($pending),
            $fields->extra(),
        );
    }
}
