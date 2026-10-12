<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

/**
 * The value a product grants for a feature key.
 */
final readonly class ProductFeatureValue
{
    /** @internal */
    public const string SCHEMA = 'ProductFeatureValue';

    public const string KIND_BOOLEAN = 'boolean';

    public const string KIND_QUANTITY = 'quantity';

    public const string KIND_CONFIG = 'config';

    /**
     * @param string $kind one of the KIND_ constants: the type of $value
     * @param mixed $value a boolean, an integer or a configuration value, as the key's kind says
     * @param bool $retired whether the key is retired
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $key,
        public string $kind,
        public mixed $value,
        public bool $retired,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            key: $json->string('key'),
            kind: $json->string('kind'),
            value: $json->any('value'),
            retired: $json->bool('retired'),
            extra: $json->extra(),
        );
    }
}
