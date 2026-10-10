<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\WebhookDelivery;
use DateTimeInterface;

/**
 * The deliveries of a webhook subscription, as the administrator, and
 * sending one again.
 */
final readonly class WebhookDeliveries
{
    /** @internal obtain it from Client::api()->webhookDeliveries() */
    public function __construct(private Caller $caller) {}

    /**
     * @param string $subscription the subscription's reference
     * @param 'pending'|'succeeded'|'failed'|null $status only deliveries in this state
     * @param DateTimeInterface|null $since only events that occurred at or after this time
     * @return Paginator<WebhookDelivery>
     */
    #[Endpoint(Operation::ListWebhookDeliveries)]
    public function list(string $subscription, ?string $status = null, ?DateTimeInterface $since = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListWebhookDeliveries, WebhookDelivery::SCHEMA, WebhookDelivery::from(...), ['status' => $status, 'since' => $since], $limit, ['subscription' => $subscription]);
    }

    /**
     * Sends an event to the subscription again, once however often the call
     * is retried: it carries an Idempotency-Key, the caller's or a fresh one.
     *
     * @param string $webhookId the event's id, its `webhook-id`
     * @return KeyedResult<WebhookDelivery>
     */
    #[Endpoint(Operation::RedeliverWebhookDelivery)]
    public function redeliver(string $subscription, string $webhookId, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::RedeliverWebhookDelivery, WebhookDelivery::SCHEMA, WebhookDelivery::from(...), ['subscription' => $subscription, 'event' => $webhookId], [], $idempotencyKey);
    }
}
