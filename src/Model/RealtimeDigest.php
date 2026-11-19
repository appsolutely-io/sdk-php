<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class RealtimeDigest implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'RealtimeDigest';

    /**
     * @param list<RealtimeDigestOrganisation> $organisations
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public ?int $sequence,
        public array $organisations,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            sequence: $json->nullableInt('sequence'),
            organisations: array_map(RealtimeDigestOrganisation::from(...), $json->objects('organisations')),
            extra: $json->extra(),
        );
    }
}
