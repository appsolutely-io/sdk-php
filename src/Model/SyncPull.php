<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use Closure;

/**
 * What changed in one resource since a cursor: the records created or
 * changed, and the ones deleted. Keep $nextCursor and pull from it next
 * time; it is present on the last page too, as the point to resume from.
 *
 * @template-covariant T
 */
final readonly class SyncPull
{
    /**
     * The schemas of the document this is read from, one per resource.
     *
     * @internal
     */
    public const array SCHEMAS = ['ArticleSyncPull', 'FormEntrySyncPull', 'OrderSyncPull', 'PageSyncPull', 'ProductSyncPull', 'UserAddressSyncPull'];

    /**
     * @param list<T> $upserts the records created or changed
     * @param list<SyncTombstone> $tombstones the records deleted
     * @param bool $hasMore whether more changes are waiting now; pull again from $nextCursor until it is false
     * @param array<string, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public array $upserts,
        public array $tombstones,
        public string $nextCursor,
        public bool $hasMore,
        public array $extra = [],
    ) {}

    /**
     * @internal
     *
     * @template U
     *
     * @param Closure(Fields): U $record reads one record of the resource
     * @return self<U>
     */
    public static function from(#[\SensitiveParameter] Fields $json, Closure $record): self
    {
        return new self(
            upserts: array_map($record, $json->objects('upserts')),
            tombstones: array_map(SyncTombstone::from(...), $json->objects('tombstones')),
            nextCursor: $json->string('next_cursor'),
            hasMore: $json->bool('has_more'),
            extra: $json->extra(),
        );
    }
}
