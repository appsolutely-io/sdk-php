<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class DistributionVersion implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'DistributionVersion';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $distribution,
        public ?string $minSupportedVersion,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            distribution: $json->string('distribution'),
            minSupportedVersion: $json->nullableString('min_supported_version'),
            extra: $json->extra(),
        );
    }
}
