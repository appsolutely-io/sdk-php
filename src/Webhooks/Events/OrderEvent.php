<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\Order;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * An order moved on: the order as the site's REST API serves it, with its
 * lines. `order.expired` is the shop giving up on an unpaid order, not a
 * cancellation; `order.revived` is an expired order back because its money
 * arrived, and is followed by an `order.paid`; `order.payment_arrived_late`
 * is money for an order that stays closed.
 */
final readonly class OrderEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::ORDER_COMPLETED,
        EventType::ORDER_SHIPPED,
        EventType::ORDER_CANCELLED,
        EventType::ORDER_STATUS_UPDATED,
        EventType::ORDER_EXPIRED,
        EventType::ORDER_REVIVED,
        EventType::ORDER_PAYMENT_ARRIVED_LATE,
    ];

    public function __construct(
        Event $envelope,
        public Order $order,
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, #[\SensitiveParameter] Fields $data): self
    {
        return new self($envelope, Order::from($data));
    }
}
