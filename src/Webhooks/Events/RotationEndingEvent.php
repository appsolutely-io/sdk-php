<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks\Events;

use Appsolutely\Sdk\Model\Fields;
use Appsolutely\Sdk\Webhooks\Event;
use Appsolutely\Sdk\Webhooks\EventType;
use DateTimeImmutable;

/**
 * `webhook.rotation_ending`: an endpoint's signing-secret rotation is about to
 * complete on its own, after which deliveries are signed with the new secret
 * alone. Make sure every verifier holds it before $completesAt.
 */
final readonly class RotationEndingEvent extends TypedEvent
{
    public const array TYPES = [
        EventType::WEBHOOK_ROTATION_ENDING,
    ];

    /**
     * @param string $endpointId the endpoint whose secret is rotating
     * @param array<array-key, mixed> $extra fields the site sent that this class does not name
     */
    public function __construct(
        Event $envelope,
        public string $endpointId,
        public DateTimeImmutable $rotationStartedAt,
        public DateTimeImmutable $completesAt,
        public array $extra = [],
    ) {
        parent::__construct($envelope);
    }

    /**
     * @internal
     */
    public static function read(Event $envelope, #[\SensitiveParameter] Fields $data): self
    {
        return new self($envelope, $data->string('endpoint_id'), $data->time('rotation_started_at'), $data->time('completes_at'), $data->extra());
    }
}
