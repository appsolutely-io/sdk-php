<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * One member of an organisation, as an owner or an admin of it lists them.
 */
final readonly class OrganisationMembership implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationMembership';

    public const string ROLE_OWNER = 'owner';
    public const string ROLE_ADMIN = 'admin';
    public const string ROLE_MEMBER = 'member';

    /**
     * @param string $membership the membership's reference, which removing, promoting and demoting name
     * @param string|null $subject the member's subject, when the site shares it with the caller
     * @param string $role one of the ROLE_ constants
     * @param DateTimeImmutable|null $adminSince when the member became an admin, null for one who is not
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $membership,
        public ?string $subject,
        public string $role,
        public DateTimeImmutable $joinedAt,
        public ?DateTimeImmutable $adminSince,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            membership: $json->string('membership'),
            subject: $json->nullableString('subject'),
            role: $json->string('role'),
            joinedAt: $json->time('joined_at'),
            adminSince: $json->nullableTime('admin_since'),
            extra: $json->extra(),
        );
    }
}
