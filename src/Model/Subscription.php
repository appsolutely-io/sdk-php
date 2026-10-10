<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

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
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
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

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        $pending = $json->nullableObject('pending_period');

        return new self(
            $json->string('id'),
            $json->string('type'),
            $json->string('status'),
            $json->string('mode'),
            $json->bool('cancel_at_period_end'),
            $json->nullableString('transition_cause'),
            $json->nullableString('recovery_reason'),
            $json->nullableTime('current_period_start'),
            $json->nullableTime('current_period_end'),
            $json->nullableTime('trial_ends_at'),
            $json->nullableTime('ended_at'),
            $json->nullableString('subject_reference'),
            $pending === null ? null : PendingPeriod::from($pending),
            $json->extra(),
        );
    }
}
