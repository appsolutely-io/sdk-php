<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * Something an account may use now, such as a plan or a number of seats.
 * What granted it is not sent.
 */
final readonly class Entitlement implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'AccountEntitlement';

    public const string KIND_TRIAL = 'trial';
    public const string KIND_PAID = 'paid';

    /**
     * @param int $quantity for a key the site totals per window, the current window's plan baseline plus every pack still live
     * @param DateTimeImmutable|null $expiresAt null when nothing on the site dates its end
     * @param DateTimeImmutable|null $periodStart the window a per-window total is for; reset a meter when this changes, not when the clock passes $periodEnd; null for every other entry
     * @param DateTimeImmutable|null $periodEnd when the window ends; null for an open run of packs with no plan, and for every entry that is not a per-window total
     * @param list<EntitlementTerm>|null $breakdown the addends of a per-window total; null for every other entry
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $key,
        public string $label,
        public int $quantity,
        public ?DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $periodStart,
        public ?DateTimeImmutable $periodEnd,
        public ?array $breakdown,
        public string $kind,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        $breakdown = $json->nullableObjects('breakdown');

        return new self(
            key: $json->string('key'),
            kind: $json->string('kind'),
            label: $json->string('label'),
            quantity: $json->int('quantity'),
            expiresAt: $json->nullableTime('expires_at'),
            periodStart: $json->nullableTime('period_start'),
            periodEnd: $json->nullableTime('period_end'),
            breakdown: $breakdown === null ? null : array_map(EntitlementTerm::from(...), $breakdown),
            extra: $json->extra(),
        );
    }
}
