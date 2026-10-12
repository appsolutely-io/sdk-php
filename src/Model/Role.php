<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A role the site's backend defined, by key.
 */
final readonly class Role
{
    /** @internal */
    public const string SCHEMA = 'Role';

    /**
     * @param bool $retired whether it can no longer be assigned
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $key,
        public bool $retired,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $retiredAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            key: $json->string('key'),
            retired: $json->bool('retired'),
            createdAt: $json->nullableTime('created_at'),
            retiredAt: $json->nullableTime('retired_at'),
            extra: $json->extra(),
        );
    }
}
