<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A country an address may be in.
 */
final readonly class AddressCountry implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'AddressCountry';

    /**
     * @param string $code the country's ISO 3166-1 alpha-2 code
     * @param DateTimeImmutable|null $retiredAt when the country stopped being offered, null while it is
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $code,
        public string $name,
        public ?DateTimeImmutable $retiredAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            code: $json->string('code'),
            name: $json->string('name'),
            retiredAt: $json->nullableTime('retired_at'),
            extra: $json->extra(),
        );
    }
}
