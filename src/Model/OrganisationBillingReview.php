<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OrganisationBillingReview implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationBillingReview';

    /**
     * @param list<OrganisationPoolReview> $pools
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $revision,
        public array $pools,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            revision: $json->string('revision'),
            pools: array_map(OrganisationPoolReview::from(...), $json->objects('pools')),
            extra: $json->extra(),
        );
    }
}
