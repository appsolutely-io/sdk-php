<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Exception\UnexpectedPayloadException;
use Appsolutely\Sdk\Webhooks\Data\Fields;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * A verified delivery with its `data` read into typed properties: one class
 * per family of event types that share a payload, and UnknownEvent for a
 * type this client does not know.
 *
 *     $event = TypedEvent::from($verifier->verify($body, $headers));
 *     if ($event instanceof AccountEvent) { ... $event->state->sequence ... }
 *
 * The envelope stays on every event, with `data` exactly as decoded, so a
 * field this client does not model is never out of reach.
 */
abstract readonly class TypedEvent
{
    public function __construct(
        public Event $envelope,
    ) {}

    /**
     * A type this client does not know is an UnknownEvent, never an
     * exception: the site may start sending a type before this client names it.
     *
     * @throws UnexpectedPayloadException when a known type's data does not have its shape
     */
    public static function from(Event $envelope): self
    {
        $fields = Fields::of($envelope->data, $envelope->type);

        $type = $envelope->type;

        return match (true) {
            in_array($type, ArticleEvent::TYPES, true) => ArticleEvent::read($envelope, $fields),
            in_array($type, PageEvent::TYPES, true) => PageEvent::read($envelope, $fields),
            in_array($type, FormSubmittedEvent::TYPES, true) => FormSubmittedEvent::read($envelope, $fields),
            in_array($type, OrderEvent::TYPES, true) => OrderEvent::read($envelope, $fields),
            in_array($type, OrderPaidEvent::TYPES, true) => OrderPaidEvent::read($envelope, $fields),
            in_array($type, PaymentReturnedEvent::TYPES, true) => PaymentReturnedEvent::read($envelope, $fields),
            in_array($type, SubscriptionEvent::TYPES, true) => SubscriptionEvent::read($envelope, $fields),
            in_array($type, RefundEvent::TYPES, true) => RefundEvent::read($envelope, $fields),
            in_array($type, ReferralRewardIssuedEvent::TYPES, true) => ReferralRewardIssuedEvent::read($envelope, $fields),
            in_array($type, ProductEvent::TYPES, true) => ProductEvent::read($envelope, $fields),
            in_array($type, AccountEvent::TYPES, true) => AccountEvent::read($envelope, $fields),
            $type === EventType::WEBHOOK_PING => PingEvent::read($envelope, $fields),
            default => new UnknownEvent($envelope),
        };
    }
}
