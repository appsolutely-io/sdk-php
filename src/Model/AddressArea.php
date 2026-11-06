<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * An area of a country an address may be in: a province, a city, a
 * district, as the country divides itself.
 */
final readonly class AddressArea implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'AddressArea';

    /**
     * @param string $code the area's code, which an address and a child area name
     * @param string $country the country's ISO 3166-1 alpha-2 code
     * @param string|null $parent the code of the area it lies in, null for a top-level one
     * @param string $type what kind of area it is in its country
     * @param string|null $officialCode the code the country itself gives the area, when it has one
     * @param DateTimeImmutable|null $retiredAt when the area stopped being offered, null while it is
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $code,
        public string $country,
        public ?string $parent,
        public string $type,
        public ?string $officialCode,
        public string $name,
        public ?DateTimeImmutable $retiredAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            code: $json->string('code'),
            country: $json->string('country'),
            parent: $json->nullableString('parent'),
            type: $json->string('type'),
            officialCode: $json->nullableString('official_code'),
            name: $json->string('name'),
            retiredAt: $json->nullableTime('retired_at'),
            extra: $json->extra(),
        );
    }
}
