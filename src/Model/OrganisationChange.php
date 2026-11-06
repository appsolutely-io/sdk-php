<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

final readonly class OrganisationChange implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationChange';

    public const string ACTOR_KIND_MEMBER = 'member';
    public const string ACTOR_KIND_OPERATOR = 'operator';
    public const string ACTOR_KIND_SYSTEM = 'system';
    public const string ACTOR_KIND_APPLICATION = 'application';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $id,
        public DateTimeImmutable $occurredAt,
        public string $action,
        public string $actorKind,
        public ?string $actor,
        public ?string $subject,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            occurredAt: $json->time('occurred_at'),
            action: $json->string('action'),
            actorKind: $json->string('actor_kind'),
            actor: $json->nullableString('actor'),
            subject: $json->nullableString('subject'),
            extra: $json->extra(),
        );
    }
}
