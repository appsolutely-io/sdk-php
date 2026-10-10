<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A record deleted since the cursor a sync pull was asked from.
 */
final readonly class SyncTombstone
{
    /** @internal */
    public const string SCHEMA = 'SyncTombstone';

    /**
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public string $id,
        public DateTimeImmutable $deletedAt,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            deletedAt: $json->time('deleted_at'),
            attributes: $json->all(),
        );
    }
}
