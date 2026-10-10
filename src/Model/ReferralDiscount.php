<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * What a referred friend gets off their order.
 */
final readonly class ReferralDiscount
{
    /** @internal */
    public const string SCHEMA = 'ReferralDiscount';

    /**
     * @param string $type such as `percent` or `fixed`
     * @param int $value a percentage, or an amount in the minor units of $currency
     * @param int|null $maxDiscount in the minor units of $currency
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $type,
        public int $value,
        public ?int $maxDiscount,
        public ?string $currency,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            type: $json->string('type'),
            value: $json->int('value'),
            maxDiscount: $json->nullableInt('max_discount'),
            currency: $json->nullableString('currency'),
            extra: $json->extra(),
        );
    }
}
