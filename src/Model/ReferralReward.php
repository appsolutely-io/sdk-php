<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A reward the member earned through a referral.
 */
final readonly class ReferralReward
{
    /** @internal */
    public const string SCHEMA = 'ReferralReward';

    /**
     * @param string $state such as `held`, `available` or `spent`
     * @param int $value in the minor units of $currency
     * @param string|null $code the coupon code the reward is spent with
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
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
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            type: $json->string('type'),
            state: $json->string('state'),
            value: $json->int('value'),
            currency: $json->string('currency'),
            holdUntil: $json->optionalTime('hold_until'),
            code: $json->optionalString('code'),
            expiresAt: $json->optionalTime('expires_at'),
            earnedAt: $json->optionalTime('earned_at'),
            attributes: $json->all(),
        );
    }
}
