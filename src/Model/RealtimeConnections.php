<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class RealtimeConnections implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'RealtimeConnections';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public RealtimePollingConnection $polling,
        public ?RealtimeReverbConnection $reverb,
        public ?RealtimeHubConnection $hub,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            polling: RealtimePollingConnection::from($json->object('polling')),
            reverb: ($value = $json->nullableObject('reverb')) === null ? null : RealtimeReverbConnection::from($value),
            hub: ($value = $json->nullableObject('hub')) === null ? null : RealtimeHubConnection::from($value),
            extra: $json->extra(),
        );
    }
}
