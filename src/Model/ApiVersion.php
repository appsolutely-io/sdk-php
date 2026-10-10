<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * The lifecycle of the API version the site answers: whether it is
 * current, and once it is deprecated, since when, until when, and what
 * replaces it.
 */
final readonly class ApiVersion
{
    /** @internal */
    public const string SCHEMA = 'ApiVersion';

    /**
     * @param string $version such as `v1`
     * @param string $status such as `current` or `deprecated`
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $version,
        public string $status,
        public ?DateTimeImmutable $deprecation,
        public ?DateTimeImmutable $sunset,
        public ?string $successor,
        public ?string $documentation,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            version: $json->string('version'),
            status: $json->string('status'),
            deprecation: $json->nullableTime('deprecation'),
            sunset: $json->nullableTime('sunset'),
            successor: $json->nullableString('successor'),
            documentation: $json->nullableString('documentation'),
            extra: $json->extra(),
        );
    }
}
