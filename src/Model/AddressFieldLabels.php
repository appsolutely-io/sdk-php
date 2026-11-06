<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * What a country calls the address fields it names its own way; null for a
 * field it calls by the usual name.
 */
final readonly class AddressFieldLabels implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'AddressFieldLabels';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public ?string $province,
        public ?string $postcode,
        public ?string $city,
        public ?string $town,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            province: $json->nullableString('province'),
            postcode: $json->nullableString('postcode'),
            city: $json->nullableString('city'),
            town: $json->nullableString('town'),
            extra: $json->extra(),
        );
    }
}
