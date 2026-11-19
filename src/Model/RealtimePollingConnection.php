<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class RealtimePollingConnection implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'RealtimePollingConnection';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $eventsUrl,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            eventsUrl: $json->string('events_url'),
            extra: $json->extra(),
        );
    }
}
