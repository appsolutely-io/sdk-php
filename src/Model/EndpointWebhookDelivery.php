<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A delivery of an event to one of the backend's endpoints, and how it went.
 */
final readonly class EndpointWebhookDelivery implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'EndpointWebhookDelivery';

    /**
     * @param string $endpointId the endpoint it was owed to
     * @param string $webhookId the event's id, sent as `webhook-id`: deduplicate on it, and name it to ask for a redelivery
     * @param string $type such as `order.paid`
     * @param string $status `pending`, `succeeded` or `failed`
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $endpointId,
        public string $webhookId,
        public string $type,
        public DateTimeImmutable $occurredAt,
        public string $status,
        public int $attempts,
        public ?int $lastResponseStatus,
        public ?DateTimeImmutable $nextAttemptAt,
        public ?DateTimeImmutable $deliveredAt,
        public ?DateTimeImmutable $failedAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            endpointId: $json->string('endpoint_id'),
            webhookId: $json->string('webhook_id'),
            type: $json->string('type'),
            occurredAt: $json->time('occurred_at'),
            status: $json->string('status'),
            attempts: $json->int('attempts'),
            lastResponseStatus: $json->nullableInt('last_response_status'),
            nextAttemptAt: $json->nullableTime('next_attempt_at'),
            deliveredAt: $json->nullableTime('delivered_at'),
            failedAt: $json->nullableTime('failed_at'),
            extra: $json->extra(),
        );
    }
}
