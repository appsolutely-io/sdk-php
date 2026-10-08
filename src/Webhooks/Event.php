<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Webhooks;

use Appsolutely\Sdk\Http\Json;
use DateTimeImmutable;

/**
 * One verified delivery's envelope, for any event type.
 */
final readonly class Event
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $id,
        public string $type,
        public DateTimeImmutable $timestamp,
        public int $version,
        public string $mode,
        public array $data,
    ) {}

    /**
     * @internal
     */
    public static function fromBody(string $body): self
    {
        $envelope = Json::decodeObject($body)
            ?? throw new WebhookVerificationException('The delivery body is not a JSON object, so not an event envelope.');

        $id = $envelope['id'] ?? null;
        $type = $envelope['type'] ?? null;
        $timestamp = $envelope['timestamp'] ?? null;
        $version = $envelope['version'] ?? null;
        $mode = $envelope['mode'] ?? null;
        $data = $envelope['data'] ?? null;

        if (
            !is_string($id) || $id === ''
            || !is_string($type) || $type === ''
            || !is_string($timestamp)
            || !is_int($version)
            || !is_string($mode)
            || !is_array($data) || ($data !== [] && array_is_list($data))
        ) {
            throw new WebhookVerificationException('The delivery body is not an event envelope of id, type, timestamp, version, mode and data.');
        }

        $occurredAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $timestamp);
        if ($occurredAt === false) {
            $occurredAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $timestamp);
        }
        if ($occurredAt === false) {
            throw new WebhookVerificationException('The event envelope\'s timestamp is not an ISO 8601 date-time.');
        }

        $fields = [];
        foreach ($data as $name => $value) {
            $fields[(string) $name] = $value;
        }

        return new self($id, $type, $occurredAt, $version, $mode, $fields);
    }
}
