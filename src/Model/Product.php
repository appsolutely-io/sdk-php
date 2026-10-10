<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A product of the site's catalogue. Money is an integer count of the
 * currency's minor units beside its ISO 4217 currency.
 */
final readonly class Product
{
    /** @internal */
    public const string SCHEMA = 'Product';

    /**
     * @param int|null $price in the minor units of $currency
     * @param int|null $originalPrice in the minor units of $currency
     * @param list<ProductPrice> $prices the price in each currency the product is sold in
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
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
        public int $status,
        public ?int $sort,
        public DateTimeImmutable $publishedAt,
        public ?DateTimeImmutable $expiredAt,
        public ?DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            type: $json->string('type'),
            title: $json->string('title'),
            subtitle: $json->optionalString('subtitle'),
            slug: $json->optionalString('slug'),
            cover: $json->optionalString('cover'),
            description: $json->optionalString('description'),
            keywords: $json->optionalString('keywords'),
            price: $json->optionalInt('price'),
            originalPrice: $json->optionalInt('original_price'),
            currency: $json->optionalString('currency'),
            prices: array_map(ProductPrice::from(...), $json->objects('prices')),
            status: $json->int('status'),
            sort: $json->optionalInt('sort'),
            publishedAt: $json->time('published_at'),
            expiredAt: $json->optionalTime('expired_at'),
            createdAt: $json->optionalTime('created_at'),
            updatedAt: $json->time('updated_at'),
            attributes: $json->all(),
        );
    }
}
