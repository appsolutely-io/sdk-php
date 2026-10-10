<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Webhooks\Data\Fields;
use Appsolutely\Sdk\Webhooks\Data\Product;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * `product.created`, `product.updated` and `product.deleted`: the product as
 * the site's REST API serves it; a deleted one as it was before the deletion.
 */
final readonly class ProductEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::PRODUCT_CREATED,
        EventType::PRODUCT_UPDATED,
        EventType::PRODUCT_DELETED,
    ];

    public function __construct(
        Event $envelope,
        public Product $product,
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, Fields $data): self
    {
        return new self($envelope, Product::read($data));
    }
}
