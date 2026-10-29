<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

final readonly class Organisation implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'Organisation';

    public const string ROLE_OWNER = 'owner';
    public const string ROLE_ADMIN = 'admin';
    public const string ROLE_MEMBER = 'member';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
        public bool $frozen,
        public DateTimeImmutable $joinedAt,
        public ?DateTimeImmutable $dissolvedAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            name: $json->string('name'),
            role: $json->string('role'),
            frozen: $json->bool('frozen'),
            joinedAt: $json->time('joined_at'),
            dissolvedAt: $json->nullableTime('dissolved_at'),
            extra: $json->extra(),
        );
    }
}
