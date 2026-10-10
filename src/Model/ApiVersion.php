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
     * @param array<string, mixed> $attributes the object as the site sent it, members without a property here included
     */
    public function __construct(
        public string $version,
        public string $status,
        public ?DateTimeImmutable $deprecation,
        public ?DateTimeImmutable $sunset,
        public ?string $successor,
        public ?string $documentation,
        public array $attributes,
    ) {}

    /** @internal */
    public static function from(Fields $json): self
    {
        return new self(
            version: $json->string('version'),
            status: $json->string('status'),
            deprecation: $json->optionalTime('deprecation'),
            sunset: $json->optionalTime('sunset'),
            successor: $json->optionalString('successor'),
            documentation: $json->optionalString('documentation'),
            attributes: $json->all(),
        );
    }
}
