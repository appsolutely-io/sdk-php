<?php

declare(strict_types=1);

namespace Appsolutely\Sdk\Model;

use DateTimeImmutable;

final readonly class OrganisationPoolReview implements SchemaModel
{
    /** @internal */
    public const string SCHEMA = 'OrganisationPoolReview';

    public const string PAYER_OUTCOME_OWNER_ON_CONFIRMATION = 'owner_on_confirmation';
    public const string PAYER_OUTCOME_RECIPIENT_ON_ACCEPTANCE = 'recipient_on_acceptance';
    public const string PAYER_OUTCOME_PREVIOUS_PAYER_UNTIL_PERIOD_END = 'previous_payer_until_period_end';

    /**
     * @param array<array-key, mixed> $extra
     */
    public function __construct(
        public string $id,
        public string $plan,
        public string $payerOutcome,
        public bool $requiresBinding,
        public int $quantity,
        public string $currency,
        public ?int $unitAmount,
        public ?int $tax,
        public ?int $total,
        public ?DateTimeImmutable $paidUntil,
        public ?DateTimeImmutable $nextChargeAt,
        public string $revision,
        public array $extra = [],
    ) {}

    /** @internal */
    public static function from(#[\SensitiveParameter] Fields $json): self
    {
        return new self(
            id: $json->string('id'),
            plan: $json->string('plan'),
            payerOutcome: $json->string('payer_outcome'),
            requiresBinding: $json->bool('requires_binding'),
            quantity: $json->int('quantity'),
            currency: $json->string('currency'),
            unitAmount: $json->nullableInt('unit_amount'),
            tax: $json->nullableInt('tax'),
            total: $json->nullableInt('total'),
            paidUntil: $json->nullableTime('paid_until'),
            nextChargeAt: $json->nullableTime('next_charge_at'),
            revision: $json->string('revision'),
            extra: $json->extra(),
        );
    }
}
