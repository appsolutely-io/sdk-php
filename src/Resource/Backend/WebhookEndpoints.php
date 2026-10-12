<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Resource\Backend;

use Appsolutely\Sdk\Api\Caller;
use Appsolutely\Sdk\Api\Endpoint;
use Appsolutely\Sdk\Api\KeyedResult;
use Appsolutely\Sdk\Api\Operation;
use Appsolutely\Sdk\Api\Paginator;
use Appsolutely\Sdk\Api\SiteApi;
use Appsolutely\Sdk\Model\WebhookEndpoint;
use Appsolutely\Sdk\Model\WebhookEndpointCreated;
use Appsolutely\Sdk\Model\WebhookEndpointRotation;

/**
 * The addresses the site sends the backend's webhook deliveries to.
 */
final readonly class WebhookEndpoints
{
    /** @internal obtain it from Client::backend()->webhookEndpoints() */
    public function __construct(private Caller $caller) {}

    /**
     * @return Paginator<WebhookEndpoint>
     */
    #[Endpoint(Operation::ListWebhookEndpoints)]
    public function list(int $limit = SiteApi::DEFAULT_LIMIT): Paginator
    {
        return $this->caller->list(Operation::ListWebhookEndpoints, WebhookEndpoint::SCHEMA, WebhookEndpoint::from(...), [], $limit);
    }

    /**
     * Registers an endpoint once, however often the call is retried; its
     * signing secret is in this answer only.
     *
     * @param non-empty-list<string> $eventTypes the event types to send it, such as `order.paid`
     * @return KeyedResult<WebhookEndpointCreated>
     */
    #[Endpoint(Operation::CreateWebhookEndpoint)]
    public function create(string $url, array $eventTypes, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::CreateWebhookEndpoint, WebhookEndpointCreated::SCHEMA, WebhookEndpointCreated::from(...), [], ['url' => $url, 'event_types' => $eventTypes], $idempotencyKey);
    }

    #[Endpoint(Operation::DisableWebhookEndpoint)]
    public function disable(string $id): WebhookEndpoint
    {
        return $this->caller->read(Operation::DisableWebhookEndpoint, WebhookEndpoint::SCHEMA, WebhookEndpoint::from(...), ['endpoint' => $id]);
    }

    #[Endpoint(Operation::EnableWebhookEndpoint)]
    public function enable(string $id): WebhookEndpoint
    {
        return $this->caller->read(Operation::EnableWebhookEndpoint, WebhookEndpoint::SCHEMA, WebhookEndpoint::from(...), ['endpoint' => $id]);
    }

    /**
     * Queues a `webhook.ping` to the endpoint, to check it answers.
     */
    #[Endpoint(Operation::TestDeliveriesWebhookEndpoint)]
    public function sendTest(string $id): void
    {
        $this->caller->send(Operation::TestDeliveriesWebhookEndpoint, ['endpoint' => $id]);
    }

    /**
     * Starts signing the endpoint's deliveries with a new secret beside the
     * old one; complete the rotation once every verifier knows the new one.
     *
     * @return KeyedResult<WebhookEndpointRotation>
     */
    #[Endpoint(Operation::StartWebhookEndpointsSecretRotation)]
    public function startRotation(string $id, ?string $idempotencyKey = null): KeyedResult
    {
        return $this->caller->keyed(Operation::StartWebhookEndpointsSecretRotation, WebhookEndpointRotation::SCHEMA, WebhookEndpointRotation::from(...), ['endpoint' => $id], [], $idempotencyKey);
    }

    /**
     * Stops signing with the old secret.
     *
     * @param string $rotationId the id the started rotation answered
     */
    #[Endpoint(Operation::CompleteWebhookEndpointsSecretRotation)]
    public function completeRotation(string $id, string $rotationId): WebhookEndpoint
    {
        return $this->caller->read(Operation::CompleteWebhookEndpointsSecretRotation, WebhookEndpoint::SCHEMA, WebhookEndpoint::from(...), ['endpoint' => $id], body: ['confirm' => $rotationId]);
    }

    /**
     * Stops signing with the new secret and keeps the old one.
     *
     * @param string $rotationId the id the started rotation answered
     */
    #[Endpoint(Operation::CancelWebhookEndpointsSecretRotation)]
    public function cancelRotation(string $id, string $rotationId): WebhookEndpoint
    {
        return $this->caller->read(Operation::CancelWebhookEndpointsSecretRotation, WebhookEndpoint::SCHEMA, WebhookEndpoint::from(...), ['endpoint' => $id], body: ['confirm' => $rotationId]);
    }
}
