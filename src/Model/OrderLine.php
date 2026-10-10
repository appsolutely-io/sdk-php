<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * One line of an order, priced in its currency's minor units.
 */
final readonly class OrderLine
{
    /** @internal */
    public const string SCHEMA = 'OrderLineSummary';

    /**
     * @param string|null $productId the product's id, null once the product is gone
     * @param string $currency ISO 4217
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public ?string $productId,
        public int $quantity,
        public int $price,
        public string $currency,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            productId: $json->nullableString('product_id'),
            quantity: $json->int('quantity'),
            price: $json->int('price'),
            currency: $json->string('currency'),
            extra: $json->extra(),
        );
    }
}
