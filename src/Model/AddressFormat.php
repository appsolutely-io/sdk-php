<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * The fields an address in one country is written with.
 */
final readonly class AddressFormat implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'AddressFormat';

    /**
     * @param string $country the country's ISO 3166-1 alpha-2 code
     * @param list<string> $fields the address fields the country uses, in order
     * @param list<string> $required the fields an address in it must fill
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $country,
        public array $fields,
        public array $required,
        public AddressFieldLabels $labels,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            country: $json->string('country'),
            fields: $json->strings('fields'),
            required: $json->strings('required'),
            labels: AddressFieldLabels::from($json->object('labels')),
            extra: $json->extra(),
        );
    }
}
