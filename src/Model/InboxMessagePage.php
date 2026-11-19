<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class InboxMessagePage implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'InboxMessagePage';

    /**
     * @param list<InboxMessage> $data
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public array $data,
        public ?string $nextCursor,
        public ?string $sequence,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            data: array_map(InboxMessage::from(...), $json->objects('data')),
            nextCursor: $json->nullableString('next_cursor'),
            sequence: $json->nullableString('sequence'),
            extra: $json->extra(),
        );
    }
}
