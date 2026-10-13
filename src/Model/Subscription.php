<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A subscription as the site describes it.
 */
final readonly class Subscription
{
    /** @internal */
    public const string SCHEMA = 'AccountSubscription';

    public const string STATUS_TRIALING = 'trialing';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_PAST_DUE = 'past_due';

    public const string STATUS_ON_HOLD = 'on_hold';

    public const string STATUS_INCOMPLETE = 'incomplete';

    public const string STATUS_CANCELED = 'canceled';

    public const string BILLED_BY_SITE = 'site';

    public const string BILLED_BY_APP_STORE = 'app_store';

    public const string BILLED_BY_GOOGLE_PLAY = 'google_play';

    public const string ENVIRONMENT_PRODUCTION = 'production';

    public const string ENVIRONMENT_SANDBOX = 'sandbox';

    /**
     * @param string $type the kind of record, `Subscription`
     * @param string $status one of the STATUS_ constants
     * @param string $mode `production` or `test`: the money the subscription runs on
     * @param bool $cancelAtPeriodEnd whether it stops when the current period ends
     * @param string|null $transitionCause what moved it to its current status
     * @param string|null $recoveryReason why a payment is being recovered
     * @param string|null $subjectReference what the subscription is for beyond the account it bills
     * @param string|null $product the slug of the product subscribed to
     * @param string|null $sku the slug of the plan it renews on
     * @param int $quantity the units it renews at
     * @param string $billedBy who takes the money: BILLED_BY_SITE, or the store that bills it (BILLED_BY_APP_STORE, BILLED_BY_GOOGLE_PLAY)
     * @param string|null $environment where a store bills it, the store's environment (one of the ENVIRONMENT_ constants); null where the site bills it
     * @param DateTimeImmutable|null $resumesAt when a pause ends: the store's reported resume time, or the next bill of a collection paused at the site's gateway
     * @param string|null $manageUrl where a store bills it, the store's own page for managing it; null where the site bills it
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
        public ?string $product,
        public ?string $sku,
        public int $quantity,
        public string $billedBy,
        public ?string $environment,
        public ?DateTimeImmutable $resumesAt,
        public ?string $manageUrl,
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
            $json->nullableString('product'),
            $json->nullableString('sku'),
            $json->int('quantity'),
            $json->string('billed_by'),
            $json->nullableString('environment'),
            $json->nullableTime('resumes_at'),
            $json->nullableString('manage_url'),
            $json->extra(),
        );
    }
}
