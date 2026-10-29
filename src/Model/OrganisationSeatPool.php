<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class OrganisationSeatPool implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationSeatPool';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $id,
        public ?string $product,
        public int $quantity,
        public int $scheduled,
        public int $ceiling,
        public int $occupied,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            product: $json->nullableString('product'),
            quantity: $json->int('quantity'),
            scheduled: $json->int('scheduled'),
            ceiling: $json->int('ceiling'),
            occupied: $json->int('occupied'),
            extra: $json->extra(),
        );
    }
}
