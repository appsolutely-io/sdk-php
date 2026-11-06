<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A product of the site's catalogue, as an API answer and a `product.*`
 * delivery both carry it. Money is an integer count of the currency's minor
 * units beside its ISO 4217 currency.
 */
final readonly class Product implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'Product';

    /**
     * @param string $type such as `physical`, `auto_virtual`, `manual_virtual`, `subscription` or `voucher`
     * @param string|null $cover the absolute URL the cover image is served at
     * @param int|null $price in the store currency, `$currency`; null when the product has no price there
     * @param int|null $originalPrice in the minor units of `$currency`
     * @param string|null $currency the store currency, null when `$price` is
     * @param list<ProductPrice> $prices one per currency the product is sold in, each the lowest of its skus there
     * @param list<ProductSkuPrice> $skuPrices every active sku's price in force, one per sku and currency, in the order the product lists its skus and then by currency
     * @param int $status 1 when active, 0 when not
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public ?string $subtitle,
        public ?string $slug,
        public ?string $cover,
        public ?string $description,
        public ?string $keywords,
        public ?int $price,
        public ?int $originalPrice,
        public ?string $currency,
        public array $prices,
        public array $skuPrices,
        public int $status,
        public ?int $sort,
        public DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $expiredAt,
        public ?DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            type: $json->string('type'),
            title: $json->string('title'),
            subtitle: $json->nullableString('subtitle'),
            slug: $json->nullableString('slug'),
            cover: $json->nullableString('cover'),
            description: $json->nullableString('description'),
            keywords: $json->nullableString('keywords'),
            price: $json->nullableInt('price'),
            originalPrice: $json->nullableInt('original_price'),
            currency: $json->nullableString('currency'),
            prices: array_map(ProductPrice::from(...), $json->objects('prices')),
            skuPrices: array_map(ProductSkuPrice::from(...), $json->objects('sku_prices')),
            status: $json->int('status'),
            sort: $json->nullableInt('sort'),
            publishedAt: $json->time('published_at'),
            expiredAt: $json->nullableTime('expired_at'),
            createdAt: $json->nullableTime('created_at'),
            updatedAt: $json->time('updated_at'),
            extra: $json->extra(),
        );
    }
}
