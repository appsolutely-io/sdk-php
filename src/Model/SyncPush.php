<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use Closure;

/**
 * The site's answer to a sync push: one result per mutation, in the order
 * they were sent. A mutation sent again under its mutation id is answered
 * from the site's record of it rather than applied twice.
 *
 * @template-covariant T
 */
final readonly class SyncPush
{
    /**
     * The schemas of the document this is read from, one per resource.
     *
     * @internal
     */
    public const array SCHEMAS = ['ArticleSyncPush', 'UserAddressSyncPush'];

    /**
     * @param list<SyncResult<T>> $results
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public array $results,
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
            results: array_map(static fn(Fields $result): SyncResult => SyncResult::from($result, $record), $json->objects('results')),
            extra: $json->extra(),
        );
    }
}
