<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\EndpointWebhookDelivery;
use Appsolutely\Sdk\Model\Redelivery;
use DateTimeInterface;

/**
 * What every endpoint of the backend's mode was owed.
 */
final readonly class Deliveries
{
    /** @internal obtain it from Client::backend()->deliveries() */
    public function __construct(private Caller $caller) {}

    /**
     * @param 'pending'|'succeeded'|'failed'|null $status only deliveries in this state
     * @param DateTimeInterface|null $since only events that occurred at or after this time
     * @return Paginator<EndpointWebhookDelivery>
     */
    #[Endpoint(Operation::ListDeliveries)]
    public function list(?string $status = null, ?DateTimeInterface $since = null, int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListDeliveries, EndpointWebhookDelivery::SCHEMA, EndpointWebhookDelivery::from(...), ['status' => $status, 'since' => $since], $limit);
    }

    /**
     * Sends an event again to every endpoint that was owed it, once however
     * often the call is retried.
     *
     * @param string $webhookId the event's id, its `webhook-id`
     * @return KeyedResult<Redelivery>
     */
    #[Endpoint(Operation::RedeliverDelivery)]
    public function redeliver(string $webhookId, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::RedeliverDelivery, Operation::RedeliverDelivery->value, Redelivery::from(...), ['event' => $webhookId], [], $idempotencyKey);
    }
}
