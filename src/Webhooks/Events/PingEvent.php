<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Webhooks\Data\Fields;
use Appsolutely\Sdk\Webhooks\Event;

/**
 * `webhook.ping`: the test delivery an operator sends from a subscription's
 * screen, signed like any other. Its mode is the subscription's own.
 */
final readonly class PingEvent extends TypedEvent
{
    /**
     * @param string $subscriptionId the subscription's reference, as its screen shows it
     * @param array<string, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        Event $envelope,
        public string $subscriptionId,
        public array $extra = [],
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, Fields $data): self
    {
        return new self($envelope, $data->string('subscription_id'), $data->extra());
    }
}
