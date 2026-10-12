<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * An address the site sends webhook deliveries to, and which events it takes.
 */
final readonly class WebhookEndpoint
{
    /** @internal */
    public const string SCHEMA = 'WebhookEndpoint';

    /**
     * @param list<string> $eventTypes the event types it is sent
     * @param bool $isActive whether deliveries go out to it
     * @param string|null $rotationState where a rotation of its signing secret stands, or null when none is under way
     * @param DateTimeImmutable|null $rotationStartedAt when the rotation under way began
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public string $url,
        public array $eventTypes,
        public bool $isActive,
        public ?string $rotationState,
        public ?DateTimeImmutable $rotationStartedAt,
        public ?DateTimeImmutable $lastDeliveredAt,
        public ?DateTimeImmutable $lastFailedAt,
        public ?DateTimeImmutable $createdAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            url: $json->string('url'),
            eventTypes: $json->strings('event_types'),
            isActive: $json->bool('is_active'),
            rotationState: $json->nullableString('rotation_state'),
            rotationStartedAt: $json->nullableTime('rotation_started_at'),
            lastDeliveredAt: $json->nullableTime('last_delivered_at'),
            lastFailedAt: $json->nullableTime('last_failed_at'),
            createdAt: $json->nullableTime('created_at'),
            extra: $json->extra(),
        );
    }
}
