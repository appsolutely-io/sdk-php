<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Webhooks\Data\Fields;
use Appsolutely\Sdk\Webhooks\Data\Order;
use Appsolutely\Sdk\Webhooks\Data\Payment;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * `order.paid`: an order's payment settled. Book the purchase once per
 * `$payment->reference`, not once per delivery: a revived order raises a
 * second `order.paid` for the same payment.
 */
final readonly class OrderPaidEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::ORDER_PAID,
    ];

    /**
     * @param Payment|null $payment the latest settled payment on the order
     * @param string|null $subject the buyer's account subject, null for a guest
     */
    public function __construct(
        Event $envelope,
        public Order $order,
        public ?Payment $payment,
        public ?string $subject,
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, Fields $data): self
    {
        $payment = $data->nullableObject('payment');

        return new self(
            $envelope,
            Order::read($data->except('payment', 'subject')),
            $payment === null ? null : Payment::read($payment),
            $data->nullableString('subject'),
        );
    }
}
