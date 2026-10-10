<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * What a referrer was given for a friend's payment, as a
 * `referral.reward_issued` delivery carries it: the reward at the moment it
 * was released, not the member's record of it (ReferralReward).
 *
 * A `credit` reward is a coupon only the referrer can spend: its code, the
 * order amount it needs and when it expires. A `cashback` reward is a refund
 * to the referrer that is now owed, and those three are null.
 */
final readonly class IssuedReferralReward
{
    /**
     * @param string $type ReferralReward::TYPE_CREDIT or ReferralReward::TYPE_CASHBACK
     * @param string|null $code the coupon code, which spends the credit for whoever holds it
     * @param int $value in the minor unit of `$currency`
     * @param int|null $minOrderAmount in the minor unit of `$currency`
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $type,
        public int $value,
        public string $currency,
        #[\SensitiveParameter]
        public ?string $code,
        public ?int $minOrderAmount,
        public ?DateTimeImmutable $expiresAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            $json->string('type'),
            $json->int('value'),
            $json->string('currency'),
            $json->nullableString('code'),
            $json->nullableInt('min_order_amount'),
            $json->nullableTime('expires_at'),
            $json->extra(),
        );
    }
}
