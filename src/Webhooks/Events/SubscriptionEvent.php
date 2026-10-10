<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Webhooks\Data\Fields;
use Appsolutely\Sdk\Webhooks\Data\Subscription;
use Appsolutely\Sdk\Webhooks\Data\SubscriptionPeriod;
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
     * @param SubscriptionPeriod|null $period the period the member is now in, sent with
     *                                        `subscription.started` and `subscription.renewed`
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
    public static function read(Event $envelope, Fields $data): self
    {
        $period = $data->nullableObject('period');

        return new self(
            $envelope,
            Subscription::read($data->except('period')),
            $period === null ? null : SubscriptionPeriod::read($period),
        );
    }
}
