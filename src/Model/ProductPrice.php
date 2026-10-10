<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * A product's price in one currency, in that currency's minor units
 * (`1999` with `USD` is 19.99).
 */
final readonly class ProductPrice
{
    /** @internal */
    public const string SCHEMA = 'ProductPriceSummary';

    /**
     * @param string $currency ISO 4217
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public string $currency,
        public int $price,
        public ?int $originalPrice,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            currency: $json->string('currency'),
            price: $json->int('price'),
            originalPrice: $json->optionalInt('original_price'),
            attributes: $json->all(),
        );
    }
}
