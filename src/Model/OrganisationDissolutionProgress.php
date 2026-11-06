<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * How far dissolving an organisation has got, once it has started.
 */
final readonly class OrganisationDissolutionProgress implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationDissolutionProgress';

    public const string STATE_DISSOLVING = 'dissolving';

    /**
     * @param string $state one of the STATE_ constants
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $state,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            state: $json->string('state'),
            extra: $json->extra(),
        );
    }
}
