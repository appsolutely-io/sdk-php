<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class MemberDeletionAccepted implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'MemberDeletionAccepted';

    public const string STATE_PENDING = 'pending';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $state,
        public \DateTimeImmutable $dueAt,
        public string $cancelUrl,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            state: $json->string('state'),
            dueAt: $json->time('due_at'),
            cancelUrl: $json->string('cancel_url'),
            extra: $json->extra(),
        );
    }
}
