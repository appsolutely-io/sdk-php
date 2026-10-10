<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\Order;
use Appsolutely\Sdk\Model\Payment;
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
    public static function read(Event $envelope, #[\SensitiveParameter] Fields $data): self
    {
        return new self(
            $envelope,
            Order::from($data->except('payment')),
            Payment::from($data->object('payment')),
        );
    }
}
