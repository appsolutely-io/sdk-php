<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OrganisationRoles implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationRoles';

    /**
     * @param list<string> $roles
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $organisation,
        public string $membership,
        public array $roles,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            organisation: $json->string('organisation'),
            membership: $json->string('membership'),
            roles: $json->strings('roles'),
            extra: $json->extra(),
        );
    }
}
