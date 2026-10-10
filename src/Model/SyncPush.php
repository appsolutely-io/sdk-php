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
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public array $results,
        public array $attributes,
    ) {}

    /**
     * @internal
     *
     * @template U
     *
     * @param Closure(Fields): U $record reads one record of the resource
     * @return self<U>
     */
    public static function from(Fields $json, Closure $record): self
    {
        return new self(
            results: array_map(static fn(Fields $result): SyncResult => SyncResult::from($result, $record), $json->objects('results')),
            attributes: $json->all(),
        );
    }
}
