<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

use DateTimeImmutable;

/**
 * A product as the site's REST API serves it. Amounts are in the minor unit
 * of their currency.
 */
final readonly class Product
{
    /**
     * @param string $type `physical`, `auto_virtual`, `manual_virtual`, `subscription` or `voucher`
     * @param string|null $cover the absolute URL the cover image is served at
     * @param int|null $price in the store currency, `$currency`; null when the product has no price there
     * @param string|null $currency the store currency, null when `$price` is
     * @param list<ProductPrice> $prices one per currency the product is sold in
     * @param int $status 1 when active, 0 when not
     * @param array<string, mixed> $extra fields the site sent that this class does not name
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
        public array $extra = [],
    ) {}

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self(
            $fields->string('id'),
            $fields->string('type'),
            $fields->string('title'),
            $fields->nullableString('subtitle'),
            $fields->nullableString('slug'),
            $fields->nullableString('cover'),
            $fields->nullableString('description'),
            $fields->nullableString('keywords'),
            $fields->nullableInt('price'),
            $fields->nullableInt('original_price'),
            $fields->nullableString('currency'),
            array_map(ProductPrice::read(...), $fields->objects('prices')),
            $fields->int('status'),
            $fields->nullableInt('sort'),
            $fields->time('published_at'),
            $fields->nullableTime('expired_at'),
            $fields->nullableTime('created_at'),
            $fields->time('updated_at'),
            $fields->extra(),
        );
    }
}
