<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Webhooks\Data\Fields;
use Appsolutely\Sdk\Webhooks\Data\RecordReference;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * `refund.requested` and `refund.processed`: a refund as it moves, named by
 * its reference only. The order's money all being back is `order.refunded`.
 */
final readonly class RefundEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::REFUND_REQUESTED,
        EventType::REFUND_PROCESSED,
    ];

    public function __construct(
        Event $envelope,
        public RecordReference $refund,
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, Fields $data): self
    {
        return new self($envelope, RecordReference::read($data));
    }
}
