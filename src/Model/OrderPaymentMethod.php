<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OrderPaymentMethod implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrderPaymentMethod';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $title,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            title: $json->string('title'),
            extra: $json->extra(),
        );
    }
}
