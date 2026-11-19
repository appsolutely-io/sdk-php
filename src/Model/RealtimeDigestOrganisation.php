<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class RealtimeDigestOrganisation implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'RealtimeDigestOrganisation';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $reference,
        public ?int $sequence,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            reference: $json->string('reference'),
            sequence: $json->nullableInt('sequence'),
            extra: $json->extra(),
        );
    }
}
