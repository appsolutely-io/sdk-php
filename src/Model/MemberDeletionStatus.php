<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class MemberDeletionStatus implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'MemberDeletionStatus';

    public const string STATE_NONE = 'none';
    public const string STATE_PENDING = 'pending';
    public const string STATE_BLOCKED = 'blocked';

    /**
     * @param list<DeletionBlocker> $blockers
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $state,
        public ?\DateTimeImmutable $dueAt,
        public array $blockers,
        public string $cancelUrl,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            state: $json->string('state'),
            dueAt: $json->nullableTime('due_at'),
            blockers: array_map(DeletionBlocker::from(...), $json->objects('blockers')),
            cancelUrl: $json->string('cancel_url'),
            extra: $json->extra(),
        );
    }
}
