<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\Order;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

final readonly class OrderPaymentMethodSwitchedEvent extends TypedEvent
{
    public const array TYPES = [EventType::ORDER_PAYMENT_METHOD_SWITCHED];
    public const string ACTOR_MEMBER = 'member';
    public const string ACTOR_GUEST = 'guest';

    public function __construct(
        Event $envelope,
        public Order $order,
        public ?string $fromPayment,
        public string $toPayment,
        public string $actor,
    ) {
        parent::__construct($envelope);
    }

    /** @internal */
    public static function read(Event $envelope, #[\SensitiveParameter] Fields $data): self
    {
        return new self(
            $envelope,
            Order::from($data->except('from_payment', 'to_payment', 'actor')),
            $data->nullableString('from_payment'),
            $data->string('to_payment'),
            $data->string('actor'),
        );
    }
}
