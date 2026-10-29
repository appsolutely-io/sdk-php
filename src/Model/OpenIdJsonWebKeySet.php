<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OpenIdJsonWebKeySet implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OpenIdJsonWebKeySet';

    /**
     * @param list<OpenIdJsonWebKey> $keys
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public array $keys,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            keys: array_map(OpenIdJsonWebKey::from(...), $json->objects('keys')),
            extra: $json->extra(),
        );
    }
}
