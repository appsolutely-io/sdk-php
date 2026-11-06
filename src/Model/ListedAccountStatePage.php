<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * One page of a walk through every account's state: the states, the cursor
 * of the walk's next page, and where the next walk starts.
 *
 * Reconcile by walks: page to the end, keep `$nextChangedSince`, and start
 * the next walk from it. It is the same on every page of a walk, a few
 * seconds before the walk began, so a member changed as it began may be
 * listed again by the next walk rather than missed.
 */
final readonly class ListedAccountStatePage implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'ListedAccountStatePage';

    /**
     * @param list<ListedAccountState> $states the page's `data`
     * @param string|null $nextCursor the cursor of this walk's next page, sealed and opaque; null on the last page
     * @param DateTimeImmutable $nextChangedSince where the next walk starts: pass it as `$changedSince`
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public array $states,
        public ?string $nextCursor,
        public DateTimeImmutable $nextChangedSince,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        $next = $json->nullableString('next_cursor');

        return new self(
            states: array_map(ListedAccountState::from(...), $json->objects('data')),
            nextCursor: $next === '' ? null : $next,
            nextChangedSince: $json->time('next_changed_since'),
            extra: $json->extra(),
        );
    }
}
