<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

final readonly class RealtimeEvents implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'RealtimeEvents';

    /**
     * @param list<RealtimeHint> $hints
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public array $hints,
        public int $next,
        public bool $resync,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            hints: array_map(RealtimeHint::from(...), $json->objects('hints')),
            next: $json->int('next'),
            resync: $json->bool('resync'),
            extra: $json->extra(),
        );
    }
}
