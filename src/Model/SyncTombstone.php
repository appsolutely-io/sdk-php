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
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public DateTimeImmutable $deletedAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            deletedAt: $json->time('deleted_at'),
            extra: $json->extra(),
        );
    }
}
