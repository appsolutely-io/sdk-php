<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\Refund;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * `refund.requested` when a refund is recorded, and `refund.processed` each
 * time it moves after that: approved, rejected or paid. The order's money all
 * being back is `order.refunded`.
 */
final readonly class RefundEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::REFUND_REQUESTED,
        EventType::REFUND_PROCESSED,
    ];

    public function __construct(
        Event $envelope,
        public Refund $refund,
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, #[\SensitiveParameter] Fields $data): self
    {
        return new self($envelope, Refund::from($data));
    }
}
