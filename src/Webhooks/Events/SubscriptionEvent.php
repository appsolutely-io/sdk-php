<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Model\Subscription;
use Appsolutely\Sdk\Model\SubscriptionPeriod;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;

/**
 * A subscription's life: started, renewed, a renewal due or failed, the
 * member's bank waiting on them, a stop scheduled or withdrawn, and the end.
 * `subscription.authentication_required` is not a failed payment: do not
 * tell the member to replace their card.
 */
final readonly class SubscriptionEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::SUBSCRIPTION_STARTED,
        EventType::SUBSCRIPTION_RENEWED,
        EventType::SUBSCRIPTION_PAYMENT_DUE,
        EventType::SUBSCRIPTION_PAYMENT_FAILED,
        EventType::SUBSCRIPTION_AUTHENTICATION_REQUIRED,
        EventType::SUBSCRIPTION_CANCEL_SCHEDULED,
        EventType::SUBSCRIPTION_RESUMED,
        EventType::SUBSCRIPTION_ENDED,
    ];

    /**
     * @param SubscriptionPeriod|null $period the period the member is now in: always on
     *                                        `subscription.renewed`, on `subscription.started`
     *                                        when the start opened one, null on every other type
     */
    public function __construct(
        Event $envelope,
        public Subscription $subscription,
        public ?SubscriptionPeriod $period,
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, #[\SensitiveParameter] Fields $data): self
    {
        $period = match ($envelope->type) {
            EventType::SUBSCRIPTION_RENEWED => $data->object('period'),
            EventType::SUBSCRIPTION_STARTED => $data->nullableObject('period'),
            default => null,
        };

        return new self(
            $envelope,
            Subscription::from(in_array($envelope->type, [EventType::SUBSCRIPTION_STARTED, EventType::SUBSCRIPTION_RENEWED], true) ? $data->except('period') : $data),
            $period === null ? null : SubscriptionPeriod::from($period),
        );
    }
}
