<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OrganisationRoleDefinition implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationRoleDefinition';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        #[\SensitiveParameter]
        public string $key,
        public bool $retired,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            key: $json->string('key'),
            retired: $json->bool('retired'),
            extra: $json->extra(),
        );
    }
}
