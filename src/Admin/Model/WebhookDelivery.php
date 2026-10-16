<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Admin\Model;

use Appsolutely\Sdk\Model\Fields;
use DateTimeImmutable;

/**
 * One attempt record of a webhook event to one subscription, as the Administrator API answers it.
 */
final readonly class WebhookDelivery implements AdminModel
{
    /** @internal */
    public const string SCHEMA = 'WebhookDelivery';

    public const string STATUS_PENDING = 'pending';
    public const string STATUS_SUCCEEDED = 'succeeded';
    public const string STATUS_FAILED = 'failed';

    /**
     * @param ?string $webhookEventId the `webhook-id` the delivery carried
     * @param ?string $type the event type
     * @param ?string $status one of the `STATUS_*` constants
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public ?string $webhookEventId,
        public ?string $type,
        public ?string $webhookSubscriptionId,
        public ?string $status,
        public ?int $attempts,
        public ?int $lastResponseStatus,
        public ?string $lastError,
        public ?DateTimeImmutable $nextAttemptAt,
        public ?DateTimeImmutable $deliveredAt,
        public ?DateTimeImmutable $failedAt,
        public ?DateTimeImmutable $createdAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): static
    {
        return new self(
            id: $json->string('id'),
            webhookEventId: $json->nullableString('webhook_event_id'),
            type: $json->nullableString('type'),
            webhookSubscriptionId: $json->nullableString('webhook_subscription_id'),
            status: $json->nullableString('status'),
            attempts: $json->nullableInt('attempts'),
            lastResponseStatus: $json->nullableInt('last_response_status'),
            lastError: $json->nullableString('last_error'),
            nextAttemptAt: $json->nullableTime('next_attempt_at'),
            deliveredAt: $json->nullableTime('delivered_at'),
            failedAt: $json->nullableTime('failed_at'),
            createdAt: $json->nullableTime('created_at'),
            extra: $json->extra(),
        );
    }
}
