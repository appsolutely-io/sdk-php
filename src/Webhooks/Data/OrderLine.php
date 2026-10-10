<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Data;

/**
 * One line of an order, priced in the minor unit of its currency.
 */
final readonly class OrderLine
{
    /**
     * @param string|null $productId the product's id, null once the product is gone
     * @param array<string, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        public string $id,
        public ?string $productId,
        public int $quantity,
        public int $price,
        public string $currency,
        public array $extra = [],
    ) {}

    /**
     * @internal
     */
    public static function read(#[\SensitiveParameter] Fields $fields): self
    {
        return new self(
            $fields->string('id'),
            $fields->nullableString('product_id'),
            $fields->int('quantity'),
            $fields->int('price'),
            $fields->string('currency'),
            $fields->extra(),
        );
    }
}
