<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * The roles one member holds.
 */
final readonly class RolesHeld implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'RolesHeld';

    /**
     * @param string $subject the member's public name, the `sub` its ID token carries
     * @param list<string> $roles the keys of the roles held
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $subject,
        public array $roles,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            subject: $json->string('subject'),
            roles: $json->strings('roles'),
            extra: $json->extra(),
        );
    }
}
