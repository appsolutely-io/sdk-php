<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class InboxChange implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'InboxChange';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $sequence,
        public string $kind,
        public ?string $reference,
        public ?string $throughSequence,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            sequence: $json->string('sequence'),
            kind: $json->string('kind'),
            reference: $json->nullableString('reference'),
            throughSequence: $json->nullableString('through_sequence'),
            extra: $json->extra(),
        );
    }
}
