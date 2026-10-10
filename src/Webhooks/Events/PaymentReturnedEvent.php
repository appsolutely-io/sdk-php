<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Webhooks\Data\Fields;
use Appsolutely\Sdk\Webhooks\Data\Order;
use Appsolutely\Sdk\Webhooks\Data\Payment;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * Money went back: `order.refunded` once all of an order's money is
 * returned, `payment.refunded` once all of one payment's money is, and
 * `payment.reversed` once all of one payment's money is gone with a lost
 * chargeback taking part of it. Each names the payment, so it can be matched
 * to the purchase booked from `order.paid`. One refund can raise both
 * `payment.refunded` and `payment.reversed`; take the purchase back once.
 */
final readonly class PaymentReturnedEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::ORDER_REFUNDED,
        EventType::PAYMENT_REFUNDED,
        EventType::PAYMENT_REVERSED,
    ];

    public function __construct(
        Event $envelope,
        public Order $order,
        public Payment $payment,
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, Fields $data): self
    {
        return new self(
            $envelope,
            Order::read($data->except('payment')),
            Payment::read($data->object('payment')),
        );
    }
}
