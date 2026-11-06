<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * An address the site sends a member back to after a checkout or a visit to its billing pages.
 */
final readonly class ReturnTarget implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'ReturnTarget';

    /**
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $id,
        public string $url,
        public DateTimeImmutable $createdAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            url: $json->string('url'),
            createdAt: $json->time('created_at'),
            extra: $json->extra(),
        );
    }
}
