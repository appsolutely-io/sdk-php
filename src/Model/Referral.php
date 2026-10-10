<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * The member's referral code and the terms of the programme it belongs to.
 */
final readonly class Referral
{
    /** @internal */
    public const string SCHEMA = 'Referral';

    /**
     * @param string $code the code the member shares
     * @param int $minOrderAmount the smallest order the discount applies to, in the minor units of the discount's currency
     * @param ReferralDiscount $discount what a referred friend gets off their order
     * @param ReferralTerms $reward what the member earns for a referral
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $code,
        public string $programme,
        public ReferralDiscount $discount,
        public int $minOrderAmount,
        public ReferralTerms $reward,
        #[\SensitiveParameter]
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            code: $json->string('code'),
            programme: $json->string('programme'),
            discount: ReferralDiscount::from($json->object('discount')),
            minOrderAmount: $json->int('min_order_amount'),
            reward: ReferralTerms::from($json->object('reward')),
            extra: $json->extra(),
        );
    }
}
