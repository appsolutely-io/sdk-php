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
     * @param string $membership the caller's membership of the organisation, which the member operations name
     * @param bool $seated whether the caller holds a seat in it
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $role,
        public bool $frozen,
        public DateTimeImmutable $joinedAt,
        public ?DateTimeImmutable $dissolvedAt,
        public string $membership,
        public bool $seated,
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
            membership: $json->string('membership'),
            seated: $json->bool('seated'),
            extra: $json->extra(),
        );
    }
}
