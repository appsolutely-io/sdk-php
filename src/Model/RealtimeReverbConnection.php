<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class RealtimeReverbConnection implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'RealtimeReverbConnection';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $key,
        public string $host,
        public int $port,
        public string $scheme,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            key: $json->string('key'),
            host: $json->string('host'),
            port: $json->int('port'),
            scheme: $json->string('scheme'),
            extra: $json->extra(),
        );
    }
}
