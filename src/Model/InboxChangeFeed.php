<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class InboxChangeFeed implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'InboxChangeFeed';

    /**
     * @param list<InboxChange> $changes
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public array $changes,
        public string $nextCursor,
        public bool $hasMore,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            changes: array_map(InboxChange::from(...), $json->objects('changes')),
            nextCursor: $json->string('next_cursor'),
            hasMore: $json->bool('has_more'),
            extra: $json->extra(),
        );
    }
}
