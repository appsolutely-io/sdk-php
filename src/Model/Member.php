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
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public string $id,
        public ?string $name,
        public ?string $email,
        public ?DateTimeImmutable $emailVerifiedAt,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            name: $json->optionalString('name'),
            email: $json->optionalString('email'),
            emailVerifiedAt: $json->optionalTime('email_verified_at'),
            createdAt: $json->optionalTime('created_at'),
            updatedAt: $json->optionalTime('updated_at'),
            attributes: $json->all(),
        );
    }
}
