<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class BackendMemberMessage implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'BackendMemberMessage';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $state,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            state: $json->string('state'),
            extra: $json->extra(),
        );
    }
}
