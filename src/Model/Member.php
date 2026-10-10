<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * The signed-in member.
 */
final readonly class Member
{
    /** @internal */
    public const string SCHEMA = 'Member';

    /**
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $email,
        public ?DateTimeImmutable $emailVerifiedAt,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            name: $json->nullableString('name'),
            email: $json->nullableString('email'),
            emailVerifiedAt: $json->nullableTime('email_verified_at'),
            createdAt: $json->nullableTime('created_at'),
            updatedAt: $json->nullableTime('updated_at'),
            extra: $json->extra(),
        );
    }
}
