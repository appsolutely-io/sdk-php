<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class InboxUnreadCount implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'InboxUnreadCount';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public int $unreadCount,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            unreadCount: $json->int('unread_count'),
            extra: $json->extra(),
        );
    }
}
