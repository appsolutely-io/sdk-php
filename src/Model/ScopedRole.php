<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

final readonly class ScopedRole implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'ScopedRole';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $organisation,
        #[\SensitiveParameter]
        public string $key,
        public bool $retired,
        public ?DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $retiredAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            organisation: $json->string('organisation'),
            key: $json->string('key'),
            retired: $json->bool('retired'),
            createdAt: $json->nullableTime('created_at'),
            retiredAt: $json->nullableTime('retired_at'),
            extra: $json->extra(),
        );
    }
}
