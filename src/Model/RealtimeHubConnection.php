<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class RealtimeHubConnection implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'RealtimeHubConnection';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $url,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            url: $json->string('url'),
            extra: $json->extra(),
        );
    }
}
