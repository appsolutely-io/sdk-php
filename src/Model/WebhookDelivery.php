<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * One event's delivery to a webhook subscription, and how it went.
 */
final readonly class WebhookDelivery
{
    /** @internal */
    public const string SCHEMA = 'WebhookDelivery';

    /**
     * @param string $webhookId the event's id, sent as `webhook-id`
     * @param string $type such as `order.paid`
     * @param string $status `pending`, `succeeded` or `failed`
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public string $webhookId,
        public string $type,
        public DateTimeImmutable $occurredAt,
        public string $status,
        public int $attempts,
        public ?int $lastResponseStatus,
        public ?DateTimeImmutable $nextAttemptAt,
        public ?DateTimeImmutable $deliveredAt,
        public ?DateTimeImmutable $failedAt,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            webhookId: $json->string('webhook_id'),
            type: $json->string('type'),
            occurredAt: $json->time('occurred_at'),
            status: $json->string('status'),
            attempts: $json->int('attempts'),
            lastResponseStatus: $json->optionalInt('last_response_status'),
            nextAttemptAt: $json->optionalTime('next_attempt_at'),
            deliveredAt: $json->optionalTime('delivered_at'),
            failedAt: $json->optionalTime('failed_at'),
            attributes: $json->all(),
        );
    }
}
