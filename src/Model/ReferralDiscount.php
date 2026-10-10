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
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public string $type,
        public int $value,
        public ?int $maxDiscount,
        public ?string $currency,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            type: $json->string('type'),
            value: $json->int('value'),
            maxDiscount: $json->optionalInt('max_discount'),
            currency: $json->optionalString('currency'),
            attributes: $json->all(),
        );
    }
}
