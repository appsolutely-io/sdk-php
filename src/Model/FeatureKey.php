<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * A feature a product may grant, by key, and the kind of value it holds.
 */
final readonly class FeatureKey
{
    /** @internal */
    public const string SCHEMA = 'FeatureKey';

    /**
     * @param string $kind `boolean`, `quantity` or `config`
     * @param bool $retired whether no product may be given it any longer
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $key,
        public string $kind,
        public bool $retired,
        public ?DateTimeImmutable $retiredAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            key: $json->string('key'),
            kind: $json->string('kind'),
            retired: $json->bool('retired'),
            retiredAt: $json->nullableTime('retired_at'),
            extra: $json->extra(),
        );
    }
}
