<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

use DateTimeImmutable;

/**
 * What a referrer was given for a friend's payment.
 *
 * A `credit` reward is a coupon only the referrer can spend: its code, the
 * order amount it needs and when it expires. A `cashback` reward is a refund
 * to the referrer that is now owed, and those three are null.
 */
final readonly class ReferralReward
{
    public const string TYPE_CREDIT = 'credit';
    public const string TYPE_CASHBACK = 'cashback';

    /**
     * @param string $type one of the TYPE_ constants
     * @param string|null $code the coupon code, which spends the credit for whoever holds it
     * @param int $value in the minor unit of `$currency`
     * @param int|null $minOrderAmount in the minor unit of `$currency`
     * @param array<string, mixed> $extra fields the site sent that this class does not name
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

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self(
            $fields->string('type'),
            $fields->int('value'),
            $fields->string('currency'),
            $fields->nullableString('code'),
            $fields->nullableInt('min_order_amount'),
            $fields->nullableTime('expires_at'),
            $fields->extra(),
        );
    }
}
