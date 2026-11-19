<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class RealtimeHint implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'RealtimeHint';

    /**
     * @param array<array-key, mixed> $payload
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $type,
        public \DateTimeImmutable $occurredAt,
        public int $sequence,
        public array $payload,
        public int $refetchWithinSeconds,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            type: $json->string('type'),
            occurredAt: $json->time('occurred_at'),
            sequence: $json->int('sequence'),
            payload: $json->map('payload'),
            refetchWithinSeconds: $json->int('refetch_within_seconds'),
            extra: $json->extra(),
        );
    }
}
