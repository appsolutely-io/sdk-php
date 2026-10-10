<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A reward the member earned through a referral, as the member's own list
 * of rewards carries it.
 *
 * A `credit` reward is a coupon only the referrer can spend, through its
 * code; a `cashback` reward is a refund to the referrer, and has no code.
 */
final readonly class ReferralReward
{
    /** @internal */
    public const string SCHEMA = 'ReferralReward';

    public const string TYPE_CREDIT = 'credit';

    public const string TYPE_CASHBACK = 'cashback';

    /**
     * @param string $type one of the TYPE_ constants
     * @param string $state such as `held`, `available` or `spent`
     * @param int $value in the minor units of $currency
     * @param string|null $code the coupon code, which spends the credit for whoever holds it
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $state,
        public int $value,
        public string $currency,
        public ?DateTimeImmutable $holdUntil,
        #[\SensitiveParameter]
        public ?string $code,
        public ?DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $earnedAt,
        #[\SensitiveParameter]
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            type: $json->string('type'),
            state: $json->string('state'),
            value: $json->int('value'),
            currency: $json->string('currency'),
            holdUntil: $json->nullableTime('hold_until'),
            code: $json->nullableString('code'),
            expiresAt: $json->nullableTime('expires_at'),
            earnedAt: $json->nullableTime('earned_at'),
            extra: $json->extra(),
        );
    }
}
