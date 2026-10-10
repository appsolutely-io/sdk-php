<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * One line of an order; the price is in the currency's minor units.
 */
final readonly class OrderLine
{
    /** @internal */
    public const string SCHEMA = 'OrderLineSummary';

    /**
     * @param string $currency ISO 4217
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public string $id,
        public ?string $productId,
        public int $quantity,
        public int $price,
        public string $currency,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            productId: $json->optionalString('product_id'),
            quantity: $json->int('quantity'),
            price: $json->int('price'),
            currency: $json->string('currency'),
            attributes: $json->all(),
        );
    }
}
