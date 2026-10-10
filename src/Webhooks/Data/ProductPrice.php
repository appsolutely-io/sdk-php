<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

/**
 * A product's price in one currency, in that currency's minor unit.
 */
final readonly class ProductPrice
{
    /**
     * @param array<string, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        public string $currency,
        public int $price,
        public ?int $originalPrice,
        public array $extra = [],
    ) {}

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self(
            $fields->string('currency'),
            $fields->int('price'),
            $fields->nullableInt('original_price'),
            $fields->extra(),
        );
    }
}
