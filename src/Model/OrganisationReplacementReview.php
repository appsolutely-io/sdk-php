<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

final readonly class OrganisationReplacementReview implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationReplacementReview';

    public const string TOTAL_SHOWN_FINAL = 'final';
    public const string TOTAL_SHOWN_BEFORE_PROVIDER_TAX = 'before_provider_tax';
    public const string TOTAL_SHOWN_PRICED_BY_PROVIDER = 'priced_by_provider';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $pool,
        public string $plan,
        public int $quantity,
        public string $currency,
        public int $unitAmount,
        public ?int $tax,
        public int $total,
        public string $totalShown,
        public DateTimeImmutable $firstChargeAt,
        public string $revision,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            pool: $json->string('pool'),
            plan: $json->string('plan'),
            quantity: $json->int('quantity'),
            currency: $json->string('currency'),
            unitAmount: $json->int('unit_amount'),
            tax: $json->nullableInt('tax'),
            total: $json->int('total'),
            totalShown: $json->string('total_shown'),
            firstChargeAt: $json->time('first_charge_at'),
            revision: $json->string('revision'),
            extra: $json->extra(),
        );
    }
}
