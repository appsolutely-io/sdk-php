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
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $currency,
        public int $price,
        public ?int $originalPrice,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            currency: $json->string('currency'),
            price: $json->int('price'),
            originalPrice: $json->nullableInt('original_price'),
            extra: $json->extra(),
        );
    }
}
