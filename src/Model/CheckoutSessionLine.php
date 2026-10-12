<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * One line of a checkout: a product by slug and how many.
 */
final readonly class CheckoutSessionLine
{
    /** @internal */
    public const string SCHEMA = 'CheckoutSessionLine';

    /**
     * @param string $product the product's slug
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $product,
        public int $quantity,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            product: $json->string('product'),
            quantity: $json->int('quantity'),
            extra: $json->extra(),
        );
    }
}
