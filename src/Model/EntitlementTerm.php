<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

/**
 * One addend of a quantity the site totals per window: the plan's baseline,
 * or a pack still live with the order that paid for it.
 */
final readonly class EntitlementTerm implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'AccountEntitlementTerm';

    public const string SOURCE_PLAN = 'plan';

    public const string SOURCE_PURCHASE = 'purchase';

    /**
     * @param string $source one of the SOURCE_ constants
     * @param string|null $paymentReference the reference of the order a purchase was paid by; null for the plan
     * @param DateTimeImmutable|null $endsAt when this addend stops counting; null when nothing dates its end
     * @param array<array-key, mixed> $extra the members the site sent that this class has no property for, as decoded
     */
    public function __construct(
        public string $source,
        public int $value,
        public ?string $paymentReference,
        public ?DateTimeImmutable $endsAt,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            source: $json->string('source'),
            value: $json->int('value'),
            paymentReference: $json->nullableString('payment_reference'),
            endsAt: $json->nullableTime('ends_at'),
            extra: $json->extra(),
        );
    }
}
