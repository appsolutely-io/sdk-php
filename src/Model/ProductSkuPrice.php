<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * What one of a product's skus costs in one currency, and how often it
 * renews: a product sold monthly and yearly lists one of these per sku, so
 * the two read as prices a caller can tell apart.
 */
final readonly class ProductSkuPrice implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'ProductSkuPrice';

    public const string INTERVAL_MONTH = 'month';

    public const string INTERVAL_YEAR = 'year';

    /**
     * @param string $sku the identifier a subscription's `sku`, a plan change and a checkout line name the sku by
     * @param string $currency ISO 4217
     * @param int $price in the minor units of `$currency`
     * @param int|null $originalPrice in the minor units of `$currency`
     * @param string|null $interval one of the INTERVAL_ constants: the unit a subscription sku renews in; null for a sku sold once
     * @param int|null $intervalCount how many intervals a period lasts (`3` with INTERVAL_MONTH is quarterly); null for a sku sold once
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $sku,
        public string $currency,
        public int $price,
        public ?int $originalPrice,
        public ?string $interval,
        public ?int $intervalCount,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            sku: $json->string('sku'),
            currency: $json->string('currency'),
            price: $json->int('price'),
            originalPrice: $json->nullableInt('original_price'),
            interval: $json->nullableString('interval'),
            intervalCount: $json->nullableInt('interval_count'),
            extra: $json->extra(),
        );
    }
}
