<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class RealtimeBootstrap implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'RealtimeBootstrap';

    /**
     * @param list<string> $drivers
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $memberChannel,
        public string $siteChannel,
        public array $drivers,
        public RealtimeConnections $connections,
        public int $refetchIntervalSeconds,
        public int $pollIntervalSeconds,
        public int $reconnectSpreadSeconds,
        public int $resumeMaxGapSeconds,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            memberChannel: $json->string('member_channel'),
            siteChannel: $json->string('site_channel'),
            drivers: $json->strings('drivers'),
            connections: RealtimeConnections::from($json->object('connections')),
            refetchIntervalSeconds: $json->int('refetch_interval_seconds'),
            pollIntervalSeconds: $json->int('poll_interval_seconds'),
            reconnectSpreadSeconds: $json->int('reconnect_spread_seconds'),
            resumeMaxGapSeconds: $json->int('resume_max_gap_seconds'),
            extra: $json->extra(),
        );
    }
}
