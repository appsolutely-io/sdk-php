<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * How postal codes are written in one country.
 */
final readonly class AddressPostalCode implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'AddressPostalCode';

    /**
     * @param string $country the country's ISO 3166-1 alpha-2 code
     * @param string|null $pattern a regular expression a postal code of the country matches, null when it uses none
     * @param list<string> $examples
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $country,
        public ?string $pattern,
        public array $examples,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            country: $json->string('country'),
            pattern: $json->nullableString('pattern'),
            examples: $json->strings('examples'),
            extra: $json->extra(),
        );
    }
}
