<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * One line of a checkout: a product by slug, how many, and the sku the line
 * named, if it named one.
 */
final readonly class CheckoutSessionLine implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'CheckoutSessionLine';

    /**
     * @param string $product the product's slug
     * @param string|null $sku the sku the line named, by the identifier the product lists it under in `skuPrices`; null when it named none
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $product,
        public int $quantity,
        public ?string $sku = null,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            product: $json->string('product'),
            quantity: $json->int('quantity'),
            sku: $json->nullableString('sku'),
            extra: $json->extra(),
        );
    }
}
